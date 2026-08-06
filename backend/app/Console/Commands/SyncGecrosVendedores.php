<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\GecrosVendedor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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

    private array $sellers = [];

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
        User::where('role', 'seller')
            ->get(['id', 'name'])
            ->each(function (User $user) {
                $this->sellers[$user->id] = [
                    'name' => (string) $user->name,
                    'palabras' => $this->palabras((string) $user->name),
                ];
            });
    }

    private function matchSellerPorNombre(string $nombreGecros): ?int
    {
        $palabrasGecros = $this->palabras($nombreGecros);
        $normGecros = implode(' ', $palabrasGecros);

        $exactos = [];
        $subsets = [];
        $reversos = [];
        $fuzzy = [];

        foreach ($this->sellers as $id => $seller) {
            $palabrasSeller = $seller['palabras'];
            $normSeller = implode(' ', $palabrasSeller);

            if ($normSeller === $normGecros) {
                $exactos[] = $id;
                continue;
            }

            if (count($palabrasSeller) > 1 && $this->esSubconjunto($palabrasSeller, $palabrasGecros)) {
                $subsets[] = $id;
                continue;
            }

            if (count($palabrasGecros) > 1 && $this->esSubconjunto($palabrasGecros, $palabrasSeller)) {
                $reversos[] = $id;
                continue;
            }

            $distancia = levenshtein($normSeller, $normGecros);
            if ($distancia <= 2) {
                $fuzzy[] = $id;
            }
        }

        foreach (['exactos', 'subsets', 'reversos'] as $tipo) {
            if (count($$tipo) === 1) {
                return $$tipo[0];
            }
            if (count($$tipo) > 1) {
                return null; // Ambiguo: no asignar.
            }
        }

        return count($fuzzy) === 1 ? $fuzzy[0] : null;
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
            $this->info("  DNI: venafi {$venafiId} -> {$this->sellers[$ganador]['name']} "
                . "({$cantidad} contactos)");
            $rec->user_id = $ganador;
            $rec->save();
            $vinculados++;
        }

        return $vinculados;
    }

    private function palabras(string $nombre): array
    {
        $normalizado = Str::ascii(Str::upper($nombre));
        $palabras = preg_split('/\s+/', preg_replace('/[^A-Z0-9\s]/', ' ', $normalizado) ?? '') ?? [];
        $palabras = array_values(array_filter($palabras, fn ($p) => $p !== ''));

        sort($palabras);

        return $palabras;
    }

    private function esSubconjunto(array $menor, array $mayor): bool
    {
        $counts = array_count_values($mayor);
        foreach ($menor as $palabra) {
            if (!isset($counts[$palabra]) || $counts[$palabra] <= 0) {
                return false;
            }
            $counts[$palabra]--;
        }

        return true;
    }
}
