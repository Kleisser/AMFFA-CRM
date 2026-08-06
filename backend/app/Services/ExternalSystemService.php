<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ExternalCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalSystemService
{
    /**
     * Consulta el sistema externo (GECROS) a través del API puente.
     * Nunca accede a la base de producción directamente.
     */
    public function checkAfiliado(string $dni): array
    {
        $baseUrl = rtrim((string) config('services.gecros.base_url', ''), '/');
        $apiKey = (string) config('services.gecros.api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            return $this->failure('Integración con sistema externo no configurada');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->acceptJson()
                ->get($baseUrl . '/afiliado', ['dni' => $dni]);

            if ($response->successful()) {
                $payload = $response->json();
                $found = (bool) ($payload['found'] ?? true);

                return [
                    'status' => $found ? 'found' : 'not_found',
                    'response' => $payload,
                    'error' => null,
                    'checked_at' => now(),
                ];
            }

            return $this->failure('Sistema externo respondió con error HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::warning('Error consultando sistema externo GECROS: ' . $e->getMessage());

            return $this->failure('No se pudo conectar con el sistema externo');
        }
    }

    /**
     * Consulta y guarda el resultado en external_checks (cache del KPI).
     * Devuelve null (sin persistir) si el puente no está configurado,
     * para no registrar errores falsos en el KPI.
     */
    public function checkAndRecord(Contact $contact): ?ExternalCheck
    {
        if (!config('services.gecros.base_url') || !config('services.gecros.api_key')) {
            return null;
        }

        $result = $this->checkAfiliado((string) $contact->dni);

        $externalCheck = ExternalCheck::updateOrCreate(
            ['contact_id' => $contact->id],
            [
                'dni' => $contact->dni,
                'status' => $result['status'],
                'response' => $result['response'],
                'error' => $result['error'],
                'checked_at' => $result['checked_at'],
            ]
        );

        return $externalCheck;
    }

    /**
     * Consulta altas y bajas de un período al sistema externo (GECROS).
     * Nunca accede a la base de producción directamente.
     */
    public function getAltasBajas(string $desde, string $hasta): array
    {
        $baseUrl = rtrim((string) config('services.gecros.base_url', ''), '/');
        $apiKey = (string) config('services.gecros.api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            return ['configured' => false, 'altas' => [], 'bajas' => []];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->acceptJson()
                ->get($baseUrl . '/altas-bajas', ['desde' => $desde, 'hasta' => $hasta]);

            if ($response->successful()) {
                $payload = $response->json() ?? [];

                return array_merge(['configured' => true], $payload);
            }

            Log::warning('GECROS altas-bajas respondió con error HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::warning('Error consultando GECROS altas-bajas: ' . $e->getMessage());
        }

        return ['configured' => true, 'error' => 'No se pudo consultar el sistema externo', 'altas' => [], 'bajas' => []];
    }

    public function puenteConfigurado(): bool
    {
        return !empty(config('services.gecros.base_url')) && !empty(config('services.gecros.api_key'));
    }

    private function failure(string $error): array
    {
        return [
            'status' => 'error',
            'response' => null,
            'error' => $error,
            'checked_at' => now(),
        ];
    }
}
