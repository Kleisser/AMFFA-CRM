<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Define los equipos comerciales:
 *  - Supervisor 1 = Equipo de Ignacio Anzelmo
 *  - Supervisor 2 = Equipo de Gladys Ortiz
 *  - Supervisor 3 = Equipo de Maria Detry
 *
 * Asigna vendedores a equipos según el archivo de reporte que los trae:
 *  - Contactos-reporte-MariaDetry.xlsx  -> Equipo Detry
 *  - Contactos-reporte-ventia.xlsx      -> Equipo Anzelmo (provisional)
 *  - gortiz@amffa.com.ar                -> Equipo Ortiz
 *
 * Idempotente: se puede re-ejecutar sin efectos colaterales.
 */
class AsignarEquipos extends Command
{
    protected $signature = 'equipos:asignar';

    protected $description = 'Nombra a los supervisores (Anzelmo/Ortiz/Detry) y asigna los vendedores a sus equipos';

    private const SUPERVISORES = [
        'supervisor@amffa.com.ar' => ['nombre' => 'Anzelmo Ignacio', 'equipo' => 1],
        'supervisor2@amffa.com.ar' => ['nombre' => 'Ortiz Gladys', 'equipo' => 2],
        'supervisor3@amffa.com.ar' => ['nombre' => 'Detry Maria', 'equipo' => 3],
    ];

    private const ORTIZ_VENDEDOR = 'gortiz@amffa.com.ar';

    private const ARCHIVO_VENTIA = 'storage/app/ventia_contactos.csv';

    private const ARCHIVO_MARIADETRY = 'storage/app/mariadetry_contactos.csv';

    public function handle(): int
    {
        DB::transaction(function () {
            $this->nombrarSupervisores();
            $this->asignarVendedores();
        });

        $this->table(
            ['Equipo', 'Supervisor', 'Vendedores'],
            $this->resumen()
        );

        return self::SUCCESS;
    }

    private function nombrarSupervisores(): void
    {
        foreach (self::SUPERVISORES as $email => $config) {
            User::where('email', $email)->update(['name' => $config['nombre']]);
        }
    }

    private function asignarVendedores(): void
    {
        $equipos = [];
        foreach (self::SUPERVISORES as $email => $config) {
            $equipos[$config['equipo']] = User::where('email', $email)->value('id');
        }

        $detry = $this->emailsDe(self::ARCHIVO_MARIADETRY);
        $ventia = $this->emailsDe(self::ARCHIVO_VENTIA);

        $asignaciones = [];

        foreach ($detry as $email) {
            $asignaciones[$email] = $equipos[3] ?? null;
        }

        foreach (array_diff($ventia, $detry) as $email) {
            if ($email === self::ORTIZ_VENDEDOR) {
                continue;
            }
            $asignaciones[$email] = $equipos[1] ?? null;
        }

        $asignaciones[self::ORTIZ_VENDEDOR] = $equipos[2] ?? null;

        $cantidad = 0;
        foreach ($asignaciones as $email => $supervisorId) {
            $actualizado = User::where('email', $email)
                ->where('role', 'seller')
                ->whereNull('supervisor_id')
                ->update(['supervisor_id' => $supervisorId]);

            if ($actualizado > 0) {
                $cantidad++;
            }
        }

        $this->info("Vendedores asignados a equipo: {$cantidad}");
    }

    private function emailsDe(string $archivo): array
    {
        $path = base_path($archivo);
        if (!is_file($path)) {
            $this->warn("No existe el archivo {$archivo}");

            return [];
        }

        $emails = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [];
        }
        $idxVendedor = array_search('Vendedor email', $header, true);
        if ($idxVendedor !== false) {
            while (($line = fgetcsv($handle)) !== false) {
                $email = strtolower(trim((string) ($line[$idxVendedor] ?? '')));
                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }
        fclose($handle);

        return array_values(array_unique($emails));
    }

    private function resumen(): array
    {
        $rows = [];
        foreach (self::SUPERVISORES as $email => $config) {
            $sup = User::where('email', $email)->first();
            if ($sup === null) {
                continue;
            }
            $vendedores = User::where('role', 'seller')
                ->where('supervisor_id', $sup->id)
                ->count();
            $rows[] = [
                'Equipo ' . $config['equipo'],
                $sup->name,
                $vendedores,
            ];
        }

        $sinEquipo = User::where('role', 'seller')->whereNull('supervisor_id')->count();
        if ($sinEquipo > 0) {
            $rows[] = ['Sin equipo', '-', $sinEquipo];
        }

        return $rows;
    }
}
