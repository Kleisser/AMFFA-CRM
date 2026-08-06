<?php

namespace Tests\Feature;

use App\Models\GecrosVendedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VentasSyncTest extends TestCase
{
    use RefreshDatabase;

    private function credencialesFake(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        $json = json_encode([
            'type' => 'service_account',
            'project_id' => 'fake',
            'private_key' => $pem,
            'client_email' => 'ventas-sync@fake.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'gsa_');
        file_put_contents($path, $json);

        return $path;
    }

    private function configurarFake(): void
    {
        config()->set('services.ventas.sheet_range', 'Ventas!A1:Z50');
        config()->set('services.ventas.credentials', $this->credencialesFake());
    }

    public function test_sync_parsea_matriz_asesor_x_mes_y_vincula_usuarios(): void
    {
        User::factory()->create(['role' => 'seller', 'name' => 'Juan Perez']);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 3600]),
            'https://sheets.googleapis.com/v4/spreadsheets/planilla-a/*' => Http::response(['values' => [
                ['Asesor', 'jul-26', '2026-08', 'ene-26'],
                ['Juan Perez', '1.234,50', '1000', ''],
                ['Nombre Desconocido', '500', '200', ''],
                ['Total', '1.734,50', '1.200', ''],
            ]]),
        ]);

        $this->artisan('ventas:sync')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_starts_with((string) $r->url(), 'https://sheets.googleapis.com/v4/spreadsheets/planilla-a')
            && $r->hasHeader('Authorization', 'Bearer token-fake'));

        $this->assertDatabaseHas('ventas', [
            'asesor' => 'Juan Perez',
            'mes' => '2026-07',
            'monto' => '1234.50',
            'fuente' => 'planilla-a',
            'user_id' => User::where('name', 'Juan Perez')->value('id'),
        ]);
        $this->assertDatabaseHas('ventas', [
            'asesor' => 'Juan Perez',
            'mes' => '2026-08',
            'monto' => '1000.00',
        ]);
        $this->assertDatabaseHas('ventas', [
            'asesor' => 'Nombre Desconocido',
            'mes' => '2026-07',
            'user_id' => null,
        ]);

        $this->assertDatabaseMissing('ventas', ['asesor' => 'Total']);
    }

    public function test_sync_suma_el_mismo_asesor_desde_dos_planillas(): void
    {
        User::factory()->create(['role' => 'seller', 'name' => 'Ana Diaz']);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a', 'planilla-b']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 3600]),
            'https://sheets.googleapis.com/v4/spreadsheets/planilla-a/*' => Http::response(['values' => [
                ['Asesor', 'jul-26'],
                ['Ana Diaz', '400'],
            ]]),
            'https://sheets.googleapis.com/v4/spreadsheets/planilla-b/*' => Http::response(['values' => [
                ['Asesor', 'jul-26'],
                ['Ana Diaz', '600'],
            ]]),
        ]);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseHas('ventas', ['asesor' => 'Ana Diaz', 'mes' => '2026-07', 'monto' => '400.00', 'fuente' => 'planilla-a']);
        $this->assertDatabaseHas('ventas', ['asesor' => 'Ana Diaz', 'mes' => '2026-07', 'monto' => '600.00', 'fuente' => 'planilla-b']);
    }

    public function test_sync_matchea_por_catalogo_gecros_cuando_el_crm_no_coincide(): void
    {
        $vendedor = User::factory()->create(['role' => 'seller', 'name' => 'Maria de las Mercedes Blanco']);
        GecrosVendedor::create([
            'venafi_id' => 999,
            'nombre' => 'BLANCO MARIA DE LAS MERCEDES',
            'user_id' => $vendedor->id,
        ]);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 3600]),
            'https://sheets.googleapis.com/v4/spreadsheets/planilla-a/*' => Http::response(['values' => [
                ['Asesor', 'jul-26'],
                ['BLANCO MARIA DE LAS MERCEDES', '1500'],
            ]]),
        ]);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseHas('ventas', [
            'asesor' => 'BLANCO MARIA DE LAS MERCEDES',
            'mes' => '2026-07',
            'monto' => '1500.00',
            'user_id' => $vendedor->id,
        ]);
    }

    public function test_sync_sin_planilla_configurada_falla_controlado(): void
    {
        config()->set('services.ventas.spreadsheet_ids', []);

        $this->artisan('ventas:sync')->assertFailed();
    }

    public function test_sync_con_formato_de_mes_alternativo(): void
    {
        User::factory()->create(['role' => 'seller', 'name' => 'Ana Diaz']);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 3600]),
            'https://sheets.googleapis.com/v4/spreadsheets/planilla-a/*' => Http::response(['values' => [
                ['Vendedor', '07/2026', 'AGO-26'],
                ['Ana Diaz', '750', '250'],
            ]]),
        ]);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseHas('ventas', ['asesor' => 'Ana Diaz', 'mes' => '2026-07', 'monto' => '750.00']);
        $this->assertDatabaseHas('ventas', ['asesor' => 'Ana Diaz', 'mes' => '2026-08', 'monto' => '250.00']);
    }
}
