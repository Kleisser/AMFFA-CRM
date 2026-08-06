<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\GecrosVendedor;
use App\Models\Zone;
use App\Models\User;
use App\Services\ExternalSystemService;
use App\Support\CierreMensual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * KPI de altas y bajas mensuales consultando GECROS vía puente.
 * Solo admin/supervisor. Las zonas y vendedores se resuelven cruzando
 * el DNI contra los contactos del CRM (los no cargados quedan "Sin zona").
 */
class AltasBajasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            return $this->handle($request);
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }

            Log::error('AltasBajas KPI error: ' . get_class($e) . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    private function handle(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isSupervisor(), 403);

        $mes = $request->get('mes') ?? CierreMensual::mesVigente();
        $cantidad = min(max((int) ($request->get('meses', 6)), 1), 12);

        if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $mes, $matches) || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
            return response()->json(['error' => 'Parámetro mes inválido'], 422);
        }

        $cierres = CierreMensual::cierres($mes, $cantidad);
        $service = app(ExternalSystemService::class);
        $bridgeConfigurado = $service->puenteConfigurado();

        $meses = [];
        $altas = [];
        $bajas = [];
        $configured = true;

        foreach ($cierres as $cierre) {
            if (!$bridgeConfigurado) {
                $data = ['configured' => false, 'altas' => [], 'bajas' => []];
            } else {
                try {
                    $data = Cache::remember(
                        'gecros.altas_bajas.' . $cierre['desde'] . '.' . $cierre['hasta'],
                        now()->addHour(),
                        function () use ($service, $cierre) {
                            $datos = $service->getAltasBajas($cierre['desde'], $cierre['hasta']);

                            // No cachear fallos: se reintentará en la próxima petición.
                            if (!empty($datos['error'])) {
                                return ['configured' => true, 'altas' => [], 'bajas' => []];
                            }

                            return $datos;
                        }
                    );
                } catch (\Throwable $e) {
                    Log::warning('Cache GECROS no disponible: ' . $e->getMessage());
                    $data = $service->getAltasBajas($cierre['desde'], $cierre['hasta']);
                }
            }

            $configured = $configured && ($data['configured'] ?? false);

            $altasPeriodo = $data['altas'] ?? [];
            $bajasPeriodo = $data['bajas'] ?? [];

            $meses[] = [
                'mes' => $cierre['mes'],
                'desde' => $cierre['desde'],
                'hasta' => $cierre['hasta'],
                'altas' => count(array_unique(array_column($altasPeriodo, 'grupo'))),
                'bajas' => count(array_unique(array_column($bajasPeriodo, 'grupo'))),
            ];

            foreach ($altasPeriodo as $row) {
                $row['tipo'] = 'alta';
                $row['mes'] = $cierre['mes'];
                $altas[] = $row;
            }
            foreach ($bajasPeriodo as $row) {
                $row['tipo'] = 'baja';
                $row['mes'] = $cierre['mes'];
                $bajas[] = $row;
            }
        }

        [$zonaPorDni] = $this->mapContactos(array_merge($altas, $bajas));
        $equipos = $this->equiposPorVendedor(array_merge($altas, $bajas));

        foreach ($altas as &$row) {
            $row['venafi_id'] = $row['vendedor']['venafi_id'] ?? null;
            $row['vendedor'] = $row['vendedor']['nombre'] ?? null;
            $row['zona_id'] = $zonaPorDni[$row['dni'] ?? $row['numero']] ?? null;
            $row['equipo_id'] = $equipos[$row['venafi_id']]['equipo_id'] ?? null;
            $row['equipo'] = $equipos[$row['venafi_id']]['equipo'] ?? null;
        }
        unset($row);

        foreach ($bajas as &$row) {
            $row['venafi_id'] = $row['vendedor']['venafi_id'] ?? null;
            $row['vendedor'] = $row['vendedor']['nombre'] ?? null;
            $row['zona_id'] = $zonaPorDni[$row['dni'] ?? $row['numero']] ?? null;
            $row['equipo_id'] = $equipos[$row['venafi_id']]['equipo_id'] ?? null;
            $row['equipo'] = $equipos[$row['venafi_id']]['equipo'] ?? null;
        }
        unset($row);

        return response()->json([
            'configured' => $configured,
            'mes' => $mes,
            'meses' => $meses,
            'altas' => $altas,
            'bajas' => $bajas,
            'zonas' => Zone::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Mapa DNI => zona_id a partir de los contactos del CRM (zona geográfica
     * del afiliado). El vendedor y su equipo salen de GECROS (venafi_id).
     */
    private function mapContactos(array $rows): array
    {
        $numeros = array_unique(array_filter(array_column($rows, 'dni') ?: array_column($rows, 'numero')));

        if ($numeros === []) {
            return [[]];
        }

        $zona = [];

        Contact::query()
            ->whereIn('dni', $numeros)
            ->get(['dni', 'zone_id'])
            ->each(function (Contact $contact) use (&$zona) {
                $zona[$contact->dni] = $contact->zone_id;
            });

        return [$zona];
    }

    /**
     * Mapa venafi_id => equipo (supervisor del vendedor de GECROS).
     * El vendedor queda como viene de GECROS (nombre del asesor real).
     */
    private function equiposPorVendedor(array $rows): array
    {
        $venafiIds = array_unique(array_filter(array_map(
            fn ($r) => $r['vendedor']['venafi_id'] ?? $r['venafi_id'] ?? null,
            $rows
        )));

        if ($venafiIds === []) {
            return [];
        }

        $supervisorNombre = User::where('role', 'supervisor')
            ->pluck('name', 'id')
            ->all();

        $equipos = [];
        GecrosVendedor::query()
            ->with('user:id,supervisor_id')
            ->whereIn('venafi_id', $venafiIds)
            ->get(['venafi_id', 'user_id'])
            ->each(function (GecrosVendedor $gv) use (&$equipos, $supervisorNombre) {
                $supervisorId = $gv->user?->supervisor_id;
                $equipos[$gv->venafi_id] = [
                    'equipo_id' => $supervisorId,
                    'equipo' => $supervisorNombre[$supervisorId] ?? null,
                ];
            });

        return $equipos;
    }
}
