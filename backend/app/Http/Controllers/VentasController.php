<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use App\Support\CierreMensual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPI de ventas reales del equipo comercial (sincronizadas de Google Sheets).
 * Solo admin/supervisor. Agrupa por vendedor y por equipo (supervisor).
 */
class VentasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403);

        $mes = $request->get('mes') ?? CierreMensual::mesVigente();

        if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $mes, $matches) || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
            return response()->json(['error' => 'Parámetro mes inválido'], 422);
        }

        $configurada = (string) config('services.ventas.spreadsheet_id', '') !== '';

        $meses = Venta::query()
            ->where('monto', '>', 0)
            ->pluck('mes')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $supervisorNombre = User::where('role', 'supervisor')->pluck('name', 'id')->all();
        $equipoDe = User::where('role', 'seller')->pluck('supervisor_id', 'id')->all();

        $filas = Venta::query()
            ->where('mes', $mes)
            ->where('monto', '>', 0)
            ->get(['id', 'asesor', 'user_id', 'monto', 'sincronizada_at']);

        $total = 0;
        $porVendedor = [];
        $porEquipo = [];

        foreach ($filas as $v) {
            $total += (float) $v->monto;

            $nombreAsesor = $v->asesor;
            $userId = $v->user_id;
            if ($userId !== null) {
                $nombreAsesor = User::find($userId)?->name ?? $v->asesor;
            }

            $porVendedor[$v->asesor] = [
                'asesor' => $nombreAsesor,
                'monto' => number_format(($porVendedor[$v->asesor]['monto'] ?? 0) + (float) $v->monto, 2, '.', ''),
                'equipo_id' => $userId !== null ? ($equipoDe[$userId] ?? null) : null,
                'equipo' => $userId !== null ? ($supervisorNombre[$equipoDe[$userId] ?? null] ?? null) : null,
            ];

            $equipoId = $porVendedor[$v->asesor]['equipo_id'];
            $porEquipo[$equipoId ?? 'sin_equipo'] = [
                'equipo_id' => $equipoId,
                'equipo' => $porVendedor[$v->asesor]['equipo'] ?? null,
                'monto' => number_format(($porEquipo[$equipoId ?? 'sin_equipo']['monto'] ?? 0) + (float) $v->monto, 2, '.', ''),
            ];
        }

        $porVendedor = array_values($porVendedor);
        $porEquipo = array_values($porEquipo);

        usort($porVendedor, fn ($a, $b) => $b['monto'] <=> $a['monto']);
        usort($porEquipo, fn ($a, $b) => $b['monto'] <=> $a['monto']);

        return response()->json([
            'configurada' => $configurada,
            'mes' => $mes,
            'meses' => $meses,
            'total' => number_format($total, 2, '.', ''),
            'por_vendedor' => $porVendedor,
            'por_equipo' => $porEquipo,
            'sincronizada_at' => $filas->max(fn ($v) => $v->sincronizada_at)?->toDateTimeString(),
        ]);
    }
}
