<?php

namespace App\Console\Commands;

use App\Models\GecrosVendedor;
use App\Models\User;
use App\Models\Venta;
use App\Services\GoogleSheetsService;
use App\Support\MatcherDeNombres;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Sincroniza las ventas reales del equipo comercial desde Google Sheets.
 *
 * La planilla se espera como matriz "totales por asesor y mes":
 *   - Fila 1 (o la primera): encabezados de mes (ej. "jul-26", "2026-07",
 *     "07/2026"); la primera columna puede ser "Asesor"/"Vendedor" y se omite.
 *   - Filas siguientes: nombre del asesor + montos por mes.
 *   - Se ignoran filas de totales (empiezan con "total" o sin nombre).
 *
 * Los nombres se matchean contra los usuarios del CRM (MatcherDeNombres);
 * los que no matchean se guardan igual (user_id null) para no perder datos.
 */
class SyncVentasComerciales extends Command
{
    protected $signature = 'ventas:sync';

    protected $description = 'Sincroniza ventas reales desde Google Sheets';

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

        $totalFilas = 0;
        $totalMatcheadas = 0;
        $totalMontos = 0;
        $todasSinMatch = [];
        $mesesIgnorados = [];

        foreach ($spreadsheetIds as $spreadsheetId) {
            try {
                $values = $sheets->getValues($spreadsheetId);
            } catch (\Throwable $e) {
                $this->error("Error con Google Sheets ({$spreadsheetId}): " . $e->getMessage());

                return self::FAILURE;
            }

            if ($values === null || $values === []) {
                $this->warn("La planilla {$spreadsheetId} no devolvió filas (revisar VENTAS_SHEET_RANGE).");

                continue;
            }

            $cabeceras = array_shift($values);

            $meses = [];
            foreach ($cabeceras as $i => $header) {
                $meses[$i] = $this->normalizarMes((string) $header);
            }

            $guardadas = 0;
            $matcheadas = 0;

            foreach ($values as $fila) {
                $nombre = trim((string) ($fila[0] ?? ''));
                if ($nombre === '' || $this->esFilaTotal($nombre)) {
                    continue;
                }

                $userId = $matcher->match($nombre);
                if ($userId === null && $matcherGecros !== null) {
                    $userId = $matcherGecros->match($nombre);
                }
                if ($userId !== null) {
                    $matcheadas++;
                } else {
                    $todasSinMatch[$nombre] = true;
                }

                foreach ($fila as $i => $celda) {
                    $mes = $meses[$i] ?? null;
                    if ($mes === null) {
                        if ($i > 0 && isset($cabeceras[$i])) {
                            $mesesIgnorados[(string) $cabeceras[$i]] = true;
                        }
                        continue;
                    }

                    $monto = $this->parseMonto((string) $celda);
                    if ($monto <= 0) {
                        continue;
                    }

                    Venta::updateOrCreate(
                        ['asesor' => $nombre, 'mes' => $mes, 'fuente' => $spreadsheetId],
                        ['user_id' => $userId, 'monto' => $monto, 'sincronizada_at' => now()]
                    );
                    $guardadas++;
                }
            }

            $this->info("Planilla {$spreadsheetId}: filas de asesores " . count($values)
                . " | con match: {$matcheadas} | montos guardados: {$guardadas}");

            $totalFilas += count($values);
            $totalMatcheadas += $matcheadas;
            $totalMontos += $guardadas;
        }

        $this->info("Totales: filas {$totalFilas} | con match {$totalMatcheadas} | montos {$totalMontos}");

        if ($mesesIgnorados !== []) {
            $this->warn('Columnas ignoradas (mes no reconocido): ' . implode(', ', array_keys($mesesIgnorados)));
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
     * Interpreta un monto de planilla: "1.234,56", "1234,56", "1234.56",
     * "1,234" (con coma como separador de miles o decimal).
     */
    private function parseMonto(string $celda): float
    {
        $v = trim(str_replace(['$', ' '], '', $celda));
        if ($v === '' || !preg_match('/^[\d\.,]+$/', $v)) {
            return 0;
        }

        if (str_contains($v, ',')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        return (float) $v;
    }

    private function esFilaTotal(string $nombre): bool
    {
        return preg_match('/^(total|suma|subtotal)/i', Str::ascii($nombre)) === 1;
    }

    /**
     * Convierte un encabezado de columna a "YYYY-MM".
     * Soporta: 2026-07, 07/2026, jul-26, JUL 2026, julio 2026, etc.
     */
    private function normalizarMes(string $header): ?string
    {
        $h = trim(Str::ascii(strtolower($header)));
        if ($h === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $h, $m)) {
            $mes = (int) $m[2];

            return $mes >= 1 && $mes <= 12 ? $m[1] . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) : null;
        }

        if (preg_match('/^(\d{1,2})\s*[\/\-]\s*(\d{2,4})$/', $h, $m)) {
            $mes = (int) $m[1];
            $anio = (int) $m[2];
            if ($mes < 1 || $mes > 12) {
                return null;
            }
            if ($anio < 100) {
                $anio += $anio <= 50 ? 2000 : 1900;
            }

            return $anio . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        }

        $nombres = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8,
            'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        if (preg_match('/^([a-z]+)(\s|\.|\-|\/)*(\d{2,4})?$/', $h, $m)) {
            $nombreMes = $m[1];
            $mes = $nombres[$nombreMes] ?? null;
            if ($mes === null) {
                return null;
            }

            $anio = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : (int) date('Y');
            if ($anio < 100) {
                $anio += $anio <= 50 ? 2000 : 1900;
            }

            return $anio . '-' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        }

        return null;
    }
}
