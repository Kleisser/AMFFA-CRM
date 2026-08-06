<?php

namespace App\Console\Commands;

use App\Models\GecrosVendedor;
use App\Models\User;
use App\Models\Venta;
use App\Services\GoogleSheetsService;
use App\Support\MatcherDeNombres;
use App\Support\PlanillasVentas;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sincroniza las altas del equipo comercial desde Google Sheets.
 *
 * Cada planilla tiene una pestaña por mes ("JULIO 2026", "AGOSTO 2026")
 * con una fila por alta:
 *   A: Asesor | B: Apellido y nombre | C: capitas | M: Plan | N: Mes de alta
 * La pestaña suele incluir los adelantos del mes siguiente: el período de
 * cada alta lo define la columna "Mes de alta" con el año de la pestaña.
 * La sincronización regenera todo (la planilla es la fuente de verdad).
 *
 * Los nombres de asesor se matchean contra los usuarios del CRM
 * (MatcherDeNombres) y, si no coinciden, contra el catálogo GECROS
 * vinculado; los que no matchean se guardan igual (user_id null).
 */
class SyncVentasComerciales extends Command
{
    protected $signature = 'ventas:sync';

    protected $description = 'Sincroniza altas de ventas desde Google Sheets (pestañas mensuales)';

    public function handle(GoogleSheetsService $sheets): int
    {
        $spreadsheetIds = config('services.ventas.spreadsheet_ids', []);

        if ($spreadsheetIds === []) {
            $this->error('VENTAS_SPREADSHEETS no configurado (IDs de planilla separados por coma)');

            return self::FAILURE;
        }

        $matcher = new MatcherDeNombres(
            User::where('role', 'seller')->pluck('name', 'id')->all()
        );

        // Segundo matcher contra el catálogo GECROS (vendedoresafi): las
        // planillas suelen usar esos nombres ("BLANCO MARIA DE LAS MERCEDES").
        // Matcheando el nombre del catálogo caemos al user_id ya vinculado.
        $gecrosPorUser = GecrosVendedor::whereNotNull('user_id')
            ->get(['nombre', 'user_id'])
            ->pluck('nombre', 'user_id')
            ->all();
        $matcherGecros = $gecrosPorUser !== [] ? new MatcherDeNombres($gecrosPorUser) : null;

        $totalAltas = 0;
        $totalMatcheadas = 0;
        $todasSinMatch = [];
        $pestanasIgnoradas = [];

        foreach ($spreadsheetIds as $spreadsheetId) {
            try {
                $tabs = $sheets->getTabs($spreadsheetId);
            } catch (\Throwable $e) {
                $this->error("Error con Google Sheets ({$spreadsheetId}): " . $e->getMessage());

                return self::FAILURE;
            }

            // Las pestañas vienen nuevas primero: la primera vez que aparece
            // una alta gana (es su versión más reciente, corrige re-cargas).
            $altasPorClave = [];
            $pestanasProcesadas = 0;

            foreach ($tabs as $tab) {
                $tabInfo = PlanillasVentas::parsearPestana($tab);
                if ($tabInfo === null) {
                    $pestanasIgnoradas[$tab] = true;
                    continue;
                }

                try {
                    $values = $sheets->getValues($spreadsheetId, $tab . '!A1:P2000');
                } catch (\Throwable $e) {
                    $this->warn("No se pudo leer la pestaña {$tab} ({$spreadsheetId}): " . $e->getMessage());
                    continue;
                }

                foreach ($this->filasDeAlta($values, $tabInfo, $spreadsheetId, $tab, $matcher, $matcherGecros) as $alta) {
                    $altasPorClave[$alta['clave']] ??= $alta;
                }
                $pestanasProcesadas++;
            }

            // Regeneración completa de la fuente: se borra lo anterior y se
            // guarda la versión actual de la planilla.
            Venta::where('fuente', $spreadsheetId)->delete();

            $guardadas = 0;
            $matcheadas = 0;

            foreach ($altasPorClave as $alta) {
                if ($alta['user_id'] !== null) {
                    $matcheadas++;
                } else {
                    $todasSinMatch[$alta['asesor']] = true;
                }

                Venta::create([
                    'user_id' => $alta['user_id'],
                    'asesor' => $alta['asesor'],
                    'afiliado' => $alta['afiliado'],
                    'capitas' => $alta['capitas'],
                    'plan' => $alta['plan'],
                    'mes' => $alta['mes'],
                    'tab' => $alta['tab'],
                    'fuente' => $spreadsheetId,
                    'sincronizada_at' => now(),
                ]);
                $guardadas++;
            }

            $this->info("Planilla {$spreadsheetId}: pestañas mensuales {$pestanasProcesadas}"
                . " | altas únicas: {$guardadas} | con match: {$matcheadas}");

            $totalAltas += $guardadas;
            $totalMatcheadas += $matcheadas;
        }

        $this->info("Totales: altas únicas {$totalAltas} | con match {$totalMatcheadas}");

        if ($pestanasIgnoradas !== []) {
            $this->warn('Pestañas ignoradas (no mensuales): ' . implode(', ', array_keys($pestanasIgnoradas)));
        }

        $nombresSinMatch = array_keys($todasSinMatch);
        if ($nombresSinMatch !== []) {
            $this->warn('Sin match contra usuarios del CRM (' . count($nombresSinMatch) . '):');
            foreach ($nombresSinMatch as $nombre) {
                $this->line('  - ' . $nombre);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Convierte las celdas de una pestaña en altas.
     * Busca la fila de encabezados (columna A = "Asesor"/"Vendedor") y toma
     * los datos posteriores. Una fila válida tiene asesor y afiliado.
     *
     * @param array<int, array<int, string>> $values
     * @param array{mes: int, anio: int} $tabInfo
     *
     * @return array<int, array{clave: string, asesor: string, afiliado: string, capitas: ?int, plan: ?string, mes: string, tab: string, user_id: ?int}>
     */
    private function filasDeAlta(
        array $values,
        array $tabInfo,
        string $spreadsheetId,
        string $tab,
        MatcherDeNombres $matcher,
        ?MatcherDeNombres $matcherGecros,
    ): array {
        $headerIdx = null;
        foreach ($values as $i => $fila) {
            $primera = strtoupper(Str::ascii(trim((string) ($fila[0] ?? ''))));
            if ($primera === 'ASESOR' || $primera === 'VENDEDOR') {
                $headerIdx = $i;
                break;
            }
        }

        if ($headerIdx === null) {
            return [];
        }

        $filas = [];
        foreach (array_slice($values, $headerIdx + 1) as $fila) {
            $asesor = trim((string) ($fila[0] ?? ''));
            $afiliado = trim((string) ($fila[1] ?? ''));
            if ($asesor === '' || $afiliado === '' || $this->esFilaTotal($asesor)) {
                continue;
            }

            $mesAlta = trim((string) ($fila[13] ?? ''));
            if (str_contains(strtoupper($mesAlta), 'DIF')) {
                continue;
            }

            $periodo = PlanillasVentas::periodoMes($mesAlta, $tabInfo['mes'], $tabInfo['anio']);

            $capitas = trim((string) ($fila[2] ?? ''));
            $plan = trim((string) ($fila[12] ?? ''));

            $filas[] = [
                'clave' => $spreadsheetId . '|' . $asesor . '|' . $afiliado . '|' . $capitas . '|' . $plan . '|' . $periodo,
                'asesor' => $asesor,
                'afiliado' => $afiliado,
                'capitas' => ctype_digit($capitas) ? (int) $capitas : null,
                'plan' => $plan !== '' ? $plan : null,
                'mes' => $periodo,
                'tab' => $tab,
                'user_id' => $this->matchUserId($asesor, $matcher, $matcherGecros),
            ];
        }

        return $filas;
    }

    private function matchUserId(
        string $asesor,
        MatcherDeNombres $matcher,
        ?MatcherDeNombres $matcherGecros,
    ): ?int {
        $userId = $matcher->match($asesor);
        if ($userId === null && $matcherGecros !== null) {
            $userId = $matcherGecros->match($asesor);
        }

        return $userId;
    }

    private function esFilaTotal(string $nombre): bool
    {
        return preg_match('/^(total|suma|subtotal)/i', Str::ascii($nombre)) === 1;
    }
}
