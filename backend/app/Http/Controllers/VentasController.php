<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venta;
use App\Support\CierreMensual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * KPI de ventas reales del equipo comercial (altas sincronizadas de Google
 * Sheets, una fila por afiliado). Solo admin/supervisor. Agrupa por vendedor
 * y por equipo (supervisor), contando altas y capitas.
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

        $configurada = config('services.ventas.spreadsheet_ids') !== [];

        $meses = Venta::query()
            ->pluck('mes')
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $supervisorNombre = User::where('role', 'supervisor')->pluck('name', 'id')->all();
        $equipoDe = User::where('role', 'seller')->pluck('supervisor_id', 'id')->all();

        $filas = Venta::query()
            ->where('mes', $mes)
            ->get(['id', 'asesor', 'user_id', 'capitas', 'sincronizada_at']);

        $totalAltas = 0;
        $totalCapitas = 0;
        $porVendedor = [];
        $porEquipo = [];

        foreach ($filas as $v) {
            $totalAltas++;
            $capitas = (int) ($v->capitas ?? 1);
            $totalCapitas += $capitas;

            $nombreAsesor = $v->asesor;
            $userId = $v->user_id;
            if ($userId !== null) {
                $nombreAsesor = User::find($userId)?->name ?? $v->asesor;
            }

            $porVendedor[$v->asesor] ??= [
                'asesor' => $nombreAsesor,
                'altas' => 0,
                'capitas' => 0,
                'equipo_id' => $userId !== null ? ($equipoDe[$userId] ?? null) : null,
                'equipo' => $userId !== null ? ($supervisorNombre[$equipoDe[$userId] ?? null] ?? null) : null,
            ];
            $porVendedor[$v->asesor]['altas']++;
            $porVendedor[$v->asesor]['capitas'] += $capitas;

            $equipoId = $porVendedor[$v->asesor]['equipo_id'];
            $porEquipo[$equipoId ?? 'sin_equipo'] ??= [
                'equipo_id' => $equipoId,
                'equipo' => $porVendedor[$v->asesor]['equipo'] ?? null,
                'altas' => 0,
                'capitas' => 0,
            ];
            $porEquipo[$equipoId ?? 'sin_equipo']['altas']++;
            $porEquipo[$equipoId ?? 'sin_equipo']['capitas'] += $capitas;
        }

        $porVendedor = array_values($porVendedor);
        $porEquipo = array_values($porEquipo);

        usort($porVendedor, fn ($a, $b) => $b['altas'] <=> $a['altas']);
        usort($porEquipo, fn ($a, $b) => $b['altas'] <=> $a['altas']);

        return response()->json([
            'configurada' => $configurada,
            'mes' => $mes,
            'meses' => $meses,
            'total' => $totalAltas,
            'capitas' => $totalCapitas,
            'por_vendedor' => $porVendedor,
            'por_equipo' => $porEquipo,
            'sincronizada_at' => $filas->max(fn ($v) => $v->sincronizada_at)?->toDateTimeString(),
        ]);
    }
}
