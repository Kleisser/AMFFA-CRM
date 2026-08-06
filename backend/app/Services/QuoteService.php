<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanPrice;

class QuoteService
{
    /**
     * Calcula la cuota mensual de un plan para una familia.
     * Replica la fórmula de "Control Equipo Comercial.xlsx".
     *
     * @return array{total: float, period: string, breakdown: array}
     */
    public function calculate(Plan $plan, ?string $period, ?int $titularAge, ?int $conyugeAge, array $childAges): array
    {
        $price = $this->resolvePrice($plan, $period);

        if (!$price) {
            return [
                'total' => 0,
                'period' => $period ?? '',
                'breakdown' => [],
                'error' => 'Sin precio cargado para este plan',
            ];
        }

        $structure = $price->structure;
        $breakdown = [];
        $total = 0.0;

        if (!empty($structure['manual'])) {
            $manual = (float) ($structure['manual_price'] ?? 0);
            return [
                'total' => round($manual, 2),
                'period' => $price->period,
                'breakdown' => [['label' => 'Precio manual', 'amount' => $manual]],
            ];
        }

        $adults = $structure['adults'] ?? [];
        $hasConyuge = !empty($structure['has_conyuge']);

        if ($titularAge === null) {
            return [
                'total' => 0,
                'period' => $price->period,
                'breakdown' => [],
                'error' => 'Falta la edad del titular',
            ];
        }

        $titular = $this->bracketPrice($adults, $titularAge);
        if ($titular === null) {
            return [
                'total' => 0,
                'period' => $price->period,
                'breakdown' => [],
                'error' => 'ERROR EDAD: la edad del titular supera el máximo del plan',
            ];
        }
        $breakdown[] = ['label' => 'Titular', 'amount' => $titular];
        $total += $titular;

        if ($hasConyuge && $conyugeAge !== null && $conyugeAge !== 0) {
            $conyuge = $this->bracketPrice($adults, $conyugeAge);
            if ($conyuge === null) {
                return [
                    'total' => 0,
                    'period' => $price->period,
                    'breakdown' => [],
                    'error' => 'ERROR EDAD: la edad del cónyuge supera el máximo del plan',
                ];
            }
            $breakdown[] = ['label' => 'Cónyuge', 'amount' => $conyuge];
            $total += $conyuge;
        }

        $children = $structure['children'] ?? ['mode' => 'none'];
        foreach ($childAges as $index => $age) {
            $amount = $this->childPrice($children, (int) $age, $index === 0);
            if ($amount === null) {
                return [
                    'total' => 0,
                    'period' => $price->period,
                    'breakdown' => [],
                    'error' => 'ERROR EDAD: edad de hijo fuera de rango',
                ];
            }
            $breakdown[] = ['label' => 'Hijo/a ' . ($index + 1), 'amount' => $amount];
            $total += $amount;
        }

        return [
            'total' => round($total, 2),
            'period' => $price->period,
            'breakdown' => $breakdown,
        ];
    }

    public function resolvePrice(Plan $plan, ?string $period): ?PlanPrice
    {
        if ($period) {
            return $plan->prices()->where('period', $period)->first() ?? $plan->prices()->orderByDesc('period')->first();
        }

        return $plan->prices()->orderByDesc('period')->first();
    }

    private function bracketPrice(array $adults, int $age): ?float
    {
        foreach ($adults as $bracket) {
            $maxAge = $bracket['max_age'];
            if ($maxAge === null || $age <= (int) $maxAge) {
                return (float) $bracket['price'];
            }
        }

        return null;
    }

    private function childPrice(array $children, int $age, bool $isFirst): ?float
    {
        $mode = $children['mode'] ?? 'none';

        if ($mode === 'none' || $mode === '') {
            return 0.0;
        }

        if ($mode === 'flat') {
            return (float) ($isFirst ? $children['first'] : $children['rest']);
        }

        if ($mode === 'age') {
            $freeUntil = (int) ($children['free_until'] ?? 0);
            if ($age <= $freeUntil) {
                return 0.0;
            }

            $tiers = $children['tiers'] ?? [];
            foreach ($tiers as $tier) {
                $maxAge = $tier['max_age'];
                if ($maxAge === null || $age <= (int) $maxAge) {
                    return (float) ($isFirst ? $tier['first'] : $tier['rest']);
                }
            }

            return null;
        }

        return null;
    }
}
