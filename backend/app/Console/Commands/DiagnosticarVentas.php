<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;

/**
 * Diagnóstico de la integración de ventas: lista las pestañas de cada
 * planilla configurada y muestra una vista previa de las primeras filas,
 * para verificar permisos y ajustar VENTAS_SHEET_RANGE.
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

            $preview = $sheets->getValues($spreadsheetId);
            if ($preview === null || $preview === []) {
                $this->warn('  Sin datos en el rango configurado (' . config('services.ventas.sheet_range') . ').');
            } else {
                $this->line('  Vista previa (primeras ' . min(5, count($preview)) . ' filas):');
                foreach (array_slice($preview, 0, 5) as $fila) {
                    $this->line('    | ' . implode(' | ', array_map(fn ($c) => substr((string) $c, 0, 40), $fila)));
                }
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
