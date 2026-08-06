<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use App\Support\PlanillasVentas;
use Illuminate\Console\Command;

/**
 * Diagnóstico de la integración de ventas: lista las pestañas de cada
 * planilla configurada y muestra una vista previa de la pestaña mensual
 * más reciente, para verificar permisos y mapear el formato.
 */
class DiagnosticarVentas extends Command
{
    protected $signature = 'ventas:diagnostico';

    protected $description = 'Lista pestañas y vista previa de las planillas de ventas';

    public function handle(GoogleSheetsService $sheets): int
    {
        $spreadsheetIds = config('services.ventas.spreadsheet_ids', []);

        if ($spreadsheetIds === []) {
            $this->error('VENTAS_SPREADSHEETS no configurado (IDs de planilla separados por coma)');

            return self::FAILURE;
        }

        $ok = true;

        foreach ($spreadsheetIds as $spreadsheetId) {
            try {
                $tabs = $sheets->getTabs($spreadsheetId);
            } catch (\Throwable $e) {
                $this->error("No se pudo acceder a {$spreadsheetId}: " . $e->getMessage());
                $ok = false;

                continue;
            }

            $this->info("Planilla {$spreadsheetId}");
            $this->line('  Pestañas: ' . implode(', ', $tabs));

            $pestanaMes = null;
            foreach ($tabs as $tab) {
                if (PlanillasVentas::parsearPestana($tab) !== null) {
                    $pestanaMes = $tab;
                    break;
                }
            }

            if ($pestanaMes === null) {
                $this->warn('  Sin pestañas mensuales (se espera "MES AÑO", ej. "JULIO 2026").');
                continue;
            }

            $values = $sheets->getValues($spreadsheetId, $pestanaMes . '!A1:P2000');
            $this->line("  Vista previa de \"{$pestanaMes}\" (" . count($values) . " filas):");

            foreach (array_slice($values, 0, 5) as $fila) {
                $this->line('    | ' . implode(' | ', array_map(fn ($c) => substr((string) $c, 0, 40), $fila)));
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
