<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lectura de planillas de Google (solo lectura) con una cuenta de servicio.
 * No requiere dependencias: firma el JWT (RS256) con OpenSSL y usa la
 * API REST de Google Sheets.
 */
class GoogleSheetsService
{
    private const SCOPES = 'https://www.googleapis.com/auth/spreadsheets.readonly';

    private array $credentials;

    public function __construct()
    {
        // Las credenciales se cargan recién al usarlas (getValues/accessToken),
        // para que la construcción del servicio no falle sin archivo.
        $this->credentials = [];
    }

    /**
     * Devuelve las celdas de una planilla como array de filas.
     * null si no hay planilla configurada.
     *
     * @return array<int, array<int, string>>|null
     */
    public function getValues(?string $spreadsheetId = null): ?array
    {
        $spreadsheetId = $spreadsheetId ?? (config('services.ventas.spreadsheet_ids')[0] ?? '');
        if ($spreadsheetId === '') {
            return null;
        }

        $range = (string) config('services.ventas.sheet_range', 'Ventas!A1:Z200');
        $token = $this->accessToken();

        $response = Http::timeout(30)
            ->withToken($token)
            ->get('https://sheets.googleapis.com/v4/spreadsheets/' . urlencode($spreadsheetId)
                . '/values/' . urlencode($range));

        if (!$response->successful()) {
            Log::warning('Google Sheets respondió con error HTTP ' . $response->status()
                . ' (' . $spreadsheetId . '): ' . substr((string) $response->body(), 0, 300));

            return [];
        }

        return $response->json('values') ?? [];
    }

    /**
     * Nombres de las pestañas de una planilla (para diagnóstico de integración).
     *
     * @return array<int, string>
     */
    public function getTabs(string $spreadsheetId): array
    {
        $token = $this->accessToken();

        $response = Http::timeout(30)
            ->withToken($token)
            ->get('https://sheets.googleapis.com/v4/spreadsheets/' . urlencode($spreadsheetId)
                . '?fields=sheets.properties.title');

        if (!$response->successful()) {
            throw new \RuntimeException('No se pudieron listar pestañas de ' . $spreadsheetId . ': HTTP '
                . $response->status() . ' ' . substr((string) $response->body(), 0, 200));
        }

        return collect($response->json('sheets') ?? [])
            ->pluck('properties.title')
            ->all();
    }

    private function credentials(): array
    {
        if ($this->credentials !== []) {
            return $this->credentials;
        }

        $path = (string) config('services.ventas.credentials', '');

        $json = ($path !== '' && is_file($path)) ? file_get_contents($path) : false;
        $decoded = $json !== false ? json_decode($json, true) : null;

        if (!is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new \RuntimeException('Credenciales de servicio de Google no encontradas en: ' . $path);
        }

        return $this->credentials = $decoded;
    }

    private function accessToken(): string
    {
        $credenciales = $this->credentials();
        $now = time();
        $claims = [
            'iss' => $credenciales['client_email'],
            'scope' => self::SCOPES,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $assertion = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $this->base64Url(json_encode($claims));

        $signature = '';
        openssl_sign($assertion, $signature, (string) $credenciales['private_key'], OPENSSL_ALGO_SHA256);

        $response = Http::timeout(30)
            ->asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion . '.' . $this->base64Url($signature),
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('No se pudo obtener token de Google: ' . $response->status()
                . ' ' . substr((string) $response->body(), 0, 300));
        }

        return (string) ($response->json('access_token') ?? '');
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
