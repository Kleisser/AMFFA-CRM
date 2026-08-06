<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Locality;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportVentia extends Command
{
    protected $signature = 'contacts:import-ventia
        {--file=storage/app/ventia_contactos.csv : CSV exportado de Ventia}
        {--dry-run : Analiza el archivo sin escribir en la base}
        {--no-users : No crear usuarios de vendedores}
        {--source=ventia : Origen de los contactos (ventia|mariadetry)}
        {--supervisor-email= : Email del supervisor que recibe a los vendedores del archivo}';

    protected $description = 'Importa contactos de un reporte comercial (Ventia, MariaDetry...)';

    private const ZONE_BY_VENTIA_NAME = [
        'GBA Zona norte' => 'ZONA NORTE',
        'GBA Zona sur' => 'ZONA SUR',
        'GBA Zona oeste' => 'ZONA OESTE',
        'La Plata' => 'LA PLATA',
        'Resto del pais' => 'INTERIOR',
        'CABA' => 'INTERIOR',
    ];

    private const STAGE_BY_VENTIA_ESTADO = [
        'Vendido' => 'Cierre Ganado',
        'En seguimiento' => 'En Negociación',
        'Recordatorio' => 'Contactado',
        'Recordatorio vencido' => 'Contactado',
        'Nuevo' => 'Nuevo Lead',
        'Desatendido' => 'Nuevo Lead',
        'Cerrado' => 'Cierre Perdido',
    ];

    private array $stages = [];
    private array $zonesByName = [];
    private array $localityCache = [];
    private ?int $adminId = null;
    private array $sellerIds = [];
    private array $byVentiaId = [];
    private array $byDni = [];
    private array $byPhone = [];
    private array $byEmail = [];

    private array $stats = [
        'filas' => 0,
        'creados' => 0,
        'actualizados' => 0,
        'sin_cambios' => 0,
        'sin_nombre' => 0,
        'usuarios_creados' => 0,
        'zonas' => [],
        'etapas' => [],
        'duplicados_fila' => 0,
    ];

    public function handle(): int
    {
        $file = base_path($this->option('file'));
        if (!is_file($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        $this->adminId = User::where('role', 'admin')->value('id');
        if (!$this->adminId) {
            $this->error('No hay un usuario admin para usar como created_by');
            return self::FAILURE;
        }

        PipelineStage::all()->each(function (PipelineStage $s) {
            $this->stages[$s->name] = $s->id;
        });
        Zone::all()->each(function (Zone $z) {
            $this->zonesByName[$z->name] = $z->id;
        });

        $dryRun = (bool) $this->option('dry-run');

        $rows = $this->readCsv($file);
        $this->stats['filas'] = count($rows);
        $this->info("Filas a procesar: " . $this->stats['filas'] . ($dryRun ? ' (DRY RUN, no se escribe nada)' : ''));

        $bar = $this->output->createProgressBar($this->stats['filas']);
        $bar->start();

        DB::transaction(function () use ($rows, $dryRun, $bar) {
            foreach ($rows as $row) {
                $this->importRow($row, $dryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->printSummary($dryRun);

        return self::SUCCESS;
    }

    private function readCsv(string $file): array
    {
        $rows = [];
        $header = null;
        $handle = fopen($file, 'r');
        if ($handle === false) {
            $this->error("No se pudo abrir el archivo CSV");
            exit(self::FAILURE);
        }
        while (($line = fgetcsv($handle)) !== false) {
            $line = array_map(fn ($v) => trim((string) $v), $line);
            if ($header === null) {
                $header = array_flip($line);
                continue;
            }
            $cells = array_slice($line, 0, count($header));
            if (count($cells) !== count($header)) {
                continue;
            }
            $rows[] = $cells;
        }
        fclose($handle);

        return array_map(fn ($line) => array_combine(array_keys($header), $line), $rows);
    }

    private function importRow(array $r, bool $dryRun): void
    {
        $nombre = trim(($r['Nombre'] ?? '') . ' ' . ($r['Apellido'] ?? ''));
        if ($nombre === '') {
            $this->stats['sin_nombre']++;
            return;
        }

        $ventiaId = $r['ID de Ventia'] ?? '';
        $estado = $r['Estado'] ?? '';
        $dni = $this->cleanDni($r['DNI'] ?? '');
        $phone = $this->normalizePhone($r['Teléfono'] ?? '');
        $email = $this->cleanEmail($r['Email'] ?? '');

        $contact = $this->findExisting($ventiaId, $dni, $phone, $email);

        if ($contact === null) {
            $contact = new Contact;
        }

        $zonas = $this->resolveZone($r);
        $stageId = $this->stages[self::STAGE_BY_VENTIA_ESTADO[$estado] ?? null] ?? null;
        $archived = $estado === 'Cerrado' || ($contact !== null && (bool) $contact->is_archived);

        $custom = $this->buildCustomFields($r);

        $data = [
            'name' => $nombre,
            'dni' => $dni !== '' ? $dni : ($contact->dni ?? null),
            'email' => $email !== '' ? $email : ($contact->email ?? null),
            'phone' => $phone !== '' ? $phone : ($contact->phone ?? null),
            'source' => $r['Origen'] ?? null,
            'notes' => $this->buildNotes($r),
            'address' => $r['Dirección'] ?: null,
            'latitude' => $this->validLatLng($r['Latitud'] ?? '', -90, 90),
            'longitude' => $this->validLatLng($r['Longitud'] ?? '', -180, 180),
            'zone_id' => $zonas['zone_id'],
            'locality_id' => $zonas['locality_id'],
            'pipeline_stage_id' => $stageId,
            'assigned_to' => $this->resolveSeller($r['Vendedor email'] ?? '', $r['Vendedor'] ?? ''),
            'is_archived' => $archived,
            'custom_fields' => $custom,
        ];

        $this->stats['zonas'][$zonas['zone_id'] ?? 'sin zona'] = ($this->stats['zonas'][$zonas['zone_id'] ?? 'sin zona'] ?? 0) + 1;
        $this->stats['etapas'][$stageId ?? 'sin etapa'] = ($this->stats['etapas'][$stageId ?? 'sin etapa'] ?? 0) + 1;

        if ($contact->exists) {
            $this->updateContact($contact, $data, $r);
        } else {
            if ($dryRun) {
                $this->stats['creados']++;
                return;
            }
            $contact->fill($data + [
                'created_by' => $this->adminId,
                'created_at' => $this->parseDate($r['Fecha y hora creado'] ?? ''),
            ]);
            $contact->save();
            $this->stats['creados']++;
            $this->indexContact($contact, $ventiaId, $dni, $phone, $email);
        }
    }

    private function findExisting(string $ventiaId, string $dni, string $phone, string $email): ?Contact
    {
        if ($ventiaId !== '' && isset($this->byVentiaId[$ventiaId])) {
            return $this->byVentiaId[$ventiaId];
        }
        if ($dni !== '' && isset($this->byDni[$dni])) {
            return $this->byDni[$dni];
        }
        if ($phone !== '' && isset($this->byPhone[$phone])) {
            return $this->byPhone[$phone];
        }
        if ($email !== '' && isset($this->byEmail[$email])) {
            return $this->byEmail[$email];
        }

        $query = Contact::query();
        if ($ventiaId !== '') {
            $query->where('custom_fields->ventia_id', $ventiaId);
        }
        $contact = $query->first();

        if ($contact === null && $dni !== '') {
            $contact = Contact::where('dni', $dni)->first();
        }
        if ($contact === null && $phone !== '') {
            $contact = Contact::where('phone', $phone)->first();
        }
        if ($contact === null && $email !== '') {
            $contact = Contact::where('email', $email)->first();
        }

        if ($contact !== null) {
            if (isset($this->byVentiaId[$ventiaId])) {
                $this->stats['duplicados_fila']++;
            }
            $this->indexContact($contact, $ventiaId, $dni, $phone, $email);
        }

        return $contact;
    }

    private function indexContact(Contact $contact, string $ventiaId, string $dni, string $phone, string $email): void
    {
        if ($ventiaId !== '') {
            $this->byVentiaId[$ventiaId] = $contact;
        }
        if ($dni !== '') {
            $this->byDni[$dni] = $contact;
        }
        if ($phone !== '') {
            $this->byPhone[$phone] = $contact;
        }
        if ($email !== '') {
            $this->byEmail[$email] = $contact;
        }
    }

    private function updateContact(Contact $contact, array $data, array $r): void
    {
        $dirty = false;
        foreach ($data as $key => $value) {
            $changed = false;
            if ($key === 'custom_fields') {
                $current = (array) ($contact->custom_fields ?? []);
                $merged = array_merge($current, $value);
                $changed = $merged !== $current;
                if ($changed) {
                    $contact->custom_fields = $merged;
                }
            } elseif ((string) ($contact->getRawOriginal($key) ?? '') !== (string) ($value ?? '')) {
                $changed = true;
                $contact->{$key} = $value;
            }
            $dirty = $dirty || $changed;
        }

        if (!$dirty) {
            $this->stats['sin_cambios']++;
            return;
        }
        if (!$this->option('dry-run')) {
            $contact->save();
        }
        $this->stats['actualizados']++;
    }

    private function buildCustomFields(array $r): array
    {
        $source = (string) $this->option('source');

        $keep = [
            'ventia_id' => 'ID de Ventia',
            'estado_ventia' => 'Estado',
            'etiqueta' => 'Etiqueta',
            'vendido' => 'Vendido',
            'ultimo_feedback' => 'Último feedback',
            'cierre' => 'Cierre',
            'recordatorio_activo' => 'Recordatorio activo',
            'fecha_ultimo_lead' => 'Fecha del último lead',
            'anuncio_ultimo_lead' => 'Anuncio del último lead',
            'campana_ultimo_lead' => 'Campaña del último lead',
            'formulario_ultimo_lead' => 'Formulario del último lead',
            'ultima_interaccion' => 'Última interacción',
            'visitas' => 'Visitas',
            'ultima_visita' => 'Última visita',
            'fecha_asignacion' => 'Fecha de asignación a agente',
            'departamento' => 'Departamento',
            'distrito' => 'Distrito',
            'localidad_ventia' => 'Localidad',
            'ciudad' => 'Ciudad',
            'telefono_original' => 'Teléfono',
            'telefonos_extra' => 'Teléfonos extra',
            'email_extra' => 'Emails extra',
            'vendedor_ventia' => 'Vendedor',
            'vendedor_email_ventia' => 'Vendedor email',
            'creador_nombre' => 'Creador contacto nombre',
            'creador_email' => 'Creador contacto email',
            'zona_ventia_codigo' => '¿De que zona sos?',
            'zona_ventia_nombre' => '¿Que plan te interesa?',
            'trabaja' => '¿Trabajas?',
            'obra_social' => '¿Tenes obra social?',
            'cual_obra_social' => '¿Cual obra social?',
            'pareja' => '¿Queres incluir a tu pareja?',
            'edad_pareja' => '¿Qué edad tiene tu pareja?',
            'hijos' => '¿Querés incluir a tus hijos?',
            'cantidad_hijos' => 'Cantidad de hijos',
            'edad' => 'Edad',
            'importado_de' => null,
        ];

        $out = [];
        foreach ($keep as $key => $col) {
            $out[$key] = $col === null ? $source : ($r[$col] ?? '');
        }

        return $out;
    }

    private function buildNotes(array $r): ?string
    {
        $parts = [];
        if (!empty($r['Detalle'])) {
            $parts[] = 'Detalle Ventia: ' . $r['Detalle'];
        }
        if (!empty($r['Último feedback'])) {
            $parts[] = 'Último feedback: ' . $r['Último feedback'];
        }
        return implode("\n", $parts) ?: null;
    }

    private function resolveZone(array $r): array
    {
        $localidad = $r['Localidad'] ?? '';
        $partido = ($r['Distrito'] ?? '') !== '' ? $r['Distrito'] : ($r['Departamento'] ?? '');

        $loc = $this->findLocality($localidad, $partido);
        if ($loc !== null) {
            return ['locality_id' => $loc->id, 'zone_id' => $loc->zone_id];
        }

        $zonaVentia = $r['¿Que plan te interesa?'] ?? '';
        if ($zonaVentia !== '' && array_key_exists($zonaVentia, self::ZONE_BY_VENTIA_NAME)) {
            $zoneId = $this->zonesByName[self::ZONE_BY_VENTIA_NAME[$zonaVentia]] ?? null;
            return ['locality_id' => null, 'zone_id' => $zoneId];
        }

        return ['locality_id' => null, 'zone_id' => null];
    }

    private function findLocality(string $localidad, string $partido): ?Locality
    {
        $key = $this->norm($localidad . '|' . $partido);
        if (array_key_exists($key, $this->localityCache)) {
            return $this->localityCache[$key];
        }

        $loc = null;
        if ($localidad !== '') {
            $loc = Locality::whereRaw('UPPER(name) = ?', [$this->norm($localidad)])->first();
        }
        if ($loc === null && $partido !== '') {
            $loc = Locality::whereRaw('UPPER(partido) = ?', [$this->norm($partido)])->orderBy('id')->first();
        }

        return $this->localityCache[$key] = $loc;
    }

    private function resolveSeller(string $email, string $name): ?int
    {
        $email = $this->cleanEmail($email);
        if ($email === '') {
            return null;
        }
        if (array_key_exists($email, $this->sellerIds)) {
            return $this->sellerIds[$email] ?: null;
        }

        $user = User::where('email', $email)->first();
        $supervisorId = $this->supervisorId();
        if ($user === null && !$this->option('no-users') && !$this->option('dry-run')) {
            $user = User::create([
                'name' => $name !== '' ? $name : $this->sellerName($email),
                'email' => $email,
                'password' => Str::random(32),
                'role' => 'seller',
                'supervisor_id' => $supervisorId,
                'is_active' => false,
            ]);
            $this->stats['usuarios_creados']++;
        } elseif ($user !== null && $supervisorId !== null && !$this->option('dry-run')) {
            // Define el equipo del vendedor (solo si aún no tiene uno).
            User::where('id', $user->id)->whereNull('supervisor_id')->update(['supervisor_id' => $supervisorId]);
        }

        $this->sellerIds[$email] = $user?->id ?? 0;

        return $user?->id ?? null;
    }

    private function supervisorId(): ?int
    {
        static $supervisorId = false;

        if ($supervisorId === false) {
            $email = (string) $this->option('supervisor-email');
            $supervisorId = $email !== ''
                ? User::where('email', $email)->where('role', 'supervisor')->value('id')
                : null;
        }

        return $supervisorId;
    }

    private function sellerName(string $email): string
    {
        $local = strtok($email, '@');
        $names = explode('.', $local);

        return collect($names)->map(fn ($n) => ucfirst($n))->implode(' ');
    }

    private function printSummary(bool $dryRun): void
    {
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Filas leídas', $this->stats['filas']],
                ['Creados', $this->stats['creados']],
                ['Actualizados', $this->stats['actualizados']],
                ['Sin cambios', $this->stats['sin_cambios']],
                ['Sin nombre (omitidos)', $this->stats['sin_nombre']],
                ['Duplicados dentro del archivo', $this->stats['duplicados_fila']],
                ['Usuarios vendedor creados', $this->stats['usuarios_creados']],
            ]
        );
        $this->info('Zonas asignadas: ' . json_encode($this->stats['zonas'], JSON_UNESCAPED_UNICODE));
        $this->info('Etapas asignadas: ' . json_encode($this->stats['etapas'], JSON_UNESCAPED_UNICODE));
    }

    private function cleanDni(string $dni): string
    {
        $dni = preg_replace('/\D+/', '', $dni) ?? '';
        return strlen($dni) >= 6 && strlen($dni) <= 11 ? $dni : '';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 8) {
            return '';
        }
        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }

    private function cleanEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function validLatLng(string $value, float $min, float $max): ?float
    {
        if ($value === '' || !is_numeric($value)) {
            return null;
        }
        $value = (float) $value;
        return $value >= $min && $value <= $max && $value != 0 ? $value : null;
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function norm(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }
}
