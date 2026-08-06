<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Períodos de cierre mensual de altas/bajas.
 * El cierre del mes es el día 25: el período va del día 26 del mes
 * anterior al día 25 del mes (ej: mes 2026-07 => 2026-06-26 a 2026-07-25).
 */
class CierreMensual
{
    public static function periodo(string $mes): array
    {
        [$anio, $mm] = array_map('intval', explode('-', $mes));

        $cierre = Carbon::create($anio, $mm, 25, 0, 0, 0);
        $desde = $cierre->copy()->subMonthNoOverflow()->day(26);

        return [
            'mes' => $mes,
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $cierre->format('Y-m-d'),
        ];
    }

    /**
     * Últimos $cantidad cierres a partir de $mes (el más reciente primero).
     */
    public static function cierres(string $mes, int $cantidad): array
    {
        [$anio, $mm] = array_map('intval', explode('-', $mes));

        $cierres = [];
        for ($i = $cantidad - 1; $i >= 0; $i--) {
            $m = Carbon::create($anio, $mm, 1, 0, 0, 0)->subMonthsNoOverflow($i)->format('Y-m');
            $cierres[] = self::periodo($m);
        }

        return $cierres;
    }

    /**
     * Mes del último cierre ya vencido (si hoy es 25 o más, el cierre actual;
     * si no, el del mes anterior).
     */
    public static function mesVigente(): string
    {
        $hoy = Carbon::now();

        return $hoy->day >= 25 ? $hoy->format('Y-m') : $hoy->subMonthNoOverflow()->format('Y-m');
    }
}
