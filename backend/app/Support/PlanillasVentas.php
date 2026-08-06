<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Utilidades para las planillas de ventas (Google Sheets):
 * - interpretar el nombre de una pestaña mensual ("JULIO 2026", "ENER0 2026");
 * - atribuir el período (YYYY-MM) de cada alta a partir de la columna
 *   "Mes de alta" y del año de la pestaña.
 */
class PlanillasVentas
{
    private const MESES = [
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

    /**
     * Interpreta el nombre de una pestaña mensual ("JULIO 2026").
     * Tolerante a erratas habituales: "ENER0 2026" (cero por O), espacios
     * al final ("JULIO "). Devuelve null si no parece mes + año
     * (ej. "Hoja 3", "Valores_Jun", "Copia de MAYO 2026").
     *
     * @return array{mes: int, anio: int}|null
     */
    public static function parsearPestana(string $tab): ?array
    {
        $tab = trim(preg_replace('/\s+/', ' ', $tab) ?? '');
        if (!preg_match('/^(.+?)\s+(\d{4})$/', $tab, $m)) {
            return null;
        }

        $nombreMes = strtolower(Str::ascii(str_replace('0', 'o', $m[1])));
        $mes = self::MESES[$nombreMes] ?? null;
        if ($mes === null) {
            return null;
        }

        return ['mes' => $mes, 'anio' => (int) $m[2]];
    }

    /**
     * Período (YYYY-MM) de un alta: combina la columna "Mes de alta" con el
     * año de la pestaña. Si el mes de alta viene vacío, usa el mes de la
     * pestaña. Los adelantos de enero (cargados en la pestaña de diciembre)
     * caen en el año siguiente.
     */
    public static function periodoMes(string $mesAlta, int $mesTab, int $anioTab): string
    {
        $mes = self::mesNumero($mesAlta) ?? $mesTab;

        if ($mes === 1 && $mesTab === 12) {
            $anioTab++;
        }

        return sprintf('%04d-%02d', $anioTab, $mes);
    }

    public static function mesNumero(string $nombre): ?int
    {
        $nombre = strtolower(Str::ascii(trim($nombre)));
        if ($nombre === '') {
            return null;
        }

        return self::MESES[$nombre] ?? null;
    }
}
