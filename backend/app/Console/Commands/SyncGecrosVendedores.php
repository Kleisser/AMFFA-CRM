<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\GecrosVendedor;
use App\Models\User;
use App\Support\MatcherDeNombres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Sincroniza el catálogo de vendedores de afiliación de GECROS
 * (dbo.vendedoresafi) vía el puente y los vincula con los usuarios
 * vendedores del CRM.
 *
 * Dos estrategias complementarias:
 *  1. Por nombre: coincidencia de palabras (orden indistinto, sin
 *     acentos), con tolerancia a nombres cortos (el del CRM es
 *     subconjunto del de GECROS) y a typos leves (distancia <= 2).
 *  2. Por DNI: cruza los DNI de los contactos del CRM contra
 *     doc_id=>venafi_id de GECROS; el vendedor más frecuente de cada
 *     afiliado gana. Llena solo los que quedaron sin vincular.
 *
 * Idempotente: nunca pisa una vinculación existente.
 */
class SyncGecrosVendedores extends Command
{
    protected $signature = 'gecros:vendedores-sync';

    protected $description = 'Sincroniza vendedores GECROS y los vincula con usuarios del CRM';

    private MatcherDeNombres $matcher;

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.gecros.base_url', ''), '/');
        $apiKey = (string) config('services.gecros.api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            $this->error('Puente GECROS no configurado (services.gecros.base_url/api_key)');

            return self::FAILURE;
        }

        $this->loadSellers();

        $catalogo = $this->getJson($baseUrl, $apiKey, '/vendedores', 'vendedores');
        if ($catalogo === null) {
            return self::FAILURE;
        }

        $this->info('Vendedores GECROS recibidos: ' . count($catalogo));

        $sincronizados = 0;
        $vinculadosPorNombre = 0;
        $sinMatch = [];

        foreach ($catalogo as $v) {
            $venafiId = (int) ($v['venafi_id'] ?? 0);
            $nombre = trim((string) ($v['nombre'] ?? ''));

            if ($venafiId <= 0 || $nombre === '') {
                continue;
            }

            $userId = $this->matchSellerPorNombre($nombre);
            $rec = GecrosVendedor::where('venafi_id', $venafiId)->first();
            if ($rec === null) {
                GecrosVendedor::create([
                    'venafi_id' => $venafiId,
                    'nombre' => $nombre,
                    'user_id' => $userId,
                ]);
            } else {
                $rec->nombre = $nombre;
                if ($rec->user_id === null && $userId !== null) {
                    $rec->user_id = $userId;
                }
                $rec->save();
            }
            $sincronizados++;

            if ($userId !== null) {
                $vinculadosPorNombre++;
            } else {
                $sinMatch[] = $nombre;
            }
        }

        if ($sinMatch !== []) {
            $this->warn('Sin match por nombre (' . count($sinMatch) . '):');
            foreach ($sinMatch as $nombre) {
                $this->line('  - ' . $nombre);
            }
        }

        $vinculadosPorDni = $this->vincularPorDni($baseUrl, $apiKey);

        // Los vendedores GECROS que ya no existen en producción dejan de vincularse.
        $idsEnCatalogo = array_map(fn ($v) => (int) ($v['venafi_id'] ?? 0), $catalogo);
        GecrosVendedor::whereNotIn('venafi_id', $idsEnCatalogo)->update(['user_id' => null]);

        $total = GecrosVendedor::whereNotNull('user_id')->count();
        $this->info('Sincronizados: ' . $sincronizados . ' | por nombre: ' . $vinculadosPorNombre
            . ' | por DNI: ' . $vinculadosPorDni . ' | total vinculados: ' . $total);

        return self::SUCCESS;
    }

    private function getJson(string $baseUrl, string $apiKey, string $path, string $key): ?array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->acceptJson()
                ->get($baseUrl . $path);
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar con el puente: ' . $e->getMessage());

            return null;
        }

        if (!$response->successful()) {
            $this->error('El puente respondió HTTP ' . $response->status() . ' en ' . $path);

            return null;
        }

        return $response->json($key) ?? [];
    }

    private function loadSellers(): void
    {
        $this->matcher = new MatcherDeNombres(
            User::where('role', 'seller')->pluck('name', 'id')->all()
        );
    }

    private function matchSellerPorNombre(string $nombreGecros): ?int
    {
        return $this->matcher->match($nombreGecros);
    }

    private function vincularPorDni(string $baseUrl, string $apiKey): int
    {
        $pares = $this->getJson($baseUrl, $apiKey, '/venafi-por-dni', 'pares');
        if ($pares === null) {
            return 0;
        }

        $venafiPorDni = [];
        foreach ($pares as $par) {
            $dni = (string) ($par['dni'] ?? '');
            $venafiId = (int) ($par['venafi_id'] ?? 0);
            if ($dni !== '' && $venafiId > 0) {
                $venafiPorDni[$dni] = $venafiId;
            }
        }

        $this->info('Pares DNI=>venafi_id recibidos: ' . count($venafiPorDni));

        $contador = [];
        Contact::query()
            ->whereNotNull('dni')
            ->whereNotNull('assigned_to')
            ->pluck('assigned_to', 'dni')
            ->each(function ($sellerId, $dni) use ($venafiPorDni, &$contador) {
                $venafiId = $venafiPorDni[$dni] ?? null;
                if ($venafiId === null) {
                    return;
                }
                $contador[$venafiId][$sellerId] = ($contador[$venafiId][$sellerId] ?? 0) + 1;
            });

        $vinculados = 0;
        foreach ($contador as $venafiId => $porVendedor) {
            $rec = GecrosVendedor::find($venafiId);
            if ($rec === null || $rec->user_id !== null) {
                continue;
            }
            arsort($porVendedor);
            $ganador = (int) array_key_first($porVendedor);
            $cantidad = reset($porVendedor);
            if ($cantidad < 3) {
                // Poca evidencia: dejar sin vincular antes que atribuir mal.
                continue;
            }
            $this->info("  DNI: venafi {$venafiId} -> "
                . (User::find($ganador)->name ?? "usuario {$ganador}") . " ({$cantidad} contactos)");
            $rec->user_id = $ganador;
            $rec->save();
            $vinculados++;
        }

        return $vinculados;
    }
}
