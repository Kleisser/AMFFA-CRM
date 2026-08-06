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
        config()->set('services.ventas.credentials', $this->credencialesFake());
    }

    private function planillaFake(array $tabs, array $valoresPorTab): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-fake', 'expires_in' => 3600]),
            'https://sheets.googleapis.com/v4/spreadsheets/*?fields=*' => Http::response([
                'sheets' => collect($tabs)->map(fn ($t) => ['properties' => ['title' => $t]])->all(),
            ]),
            'https://sheets.googleapis.com/v4/spreadsheets/*/values/*' => function (Request $request) use ($valoresPorTab) {
                $url = (string) $request->url();
                foreach ($valoresPorTab as $tab => $valores) {
                    if (str_contains($url, urlencode($tab))) {
                        return Http::response(['values' => $valores]);
                    }
                }

                return Http::response(['values' => []]);
            },
        ]);
    }

    private function encabezados(): array
    {
        return ['Asesor', 'Apellido y Nombre', 'capitas', 'Titu/Conyu', '', '1er H.', '2do', '3ro', '4to', '5to', '6to', '7mo', 'Plan', 'Mes de Alta'];
    }

    public function test_sync_parsea_pestanas_mensuales_y_guarda_altas(): void
    {
        $juan = User::factory()->create(['role' => 'seller', 'name' => 'Juan Perez']);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        // Pestañas nuevas primero: AGOSTO 2026 (con adelanto de septiembre)
        // y JULIO 2026 (con adelantos de agosto).
        $pestanas = ['AGOSTO 2026', 'JULIO 2026', 'Hoja 3', 'Valores_Jun'];
        $valores = [
            'AGOSTO 2026' => [
                ['', '', '', 'Edades'],
                $this->encabezados(),
                ['Juan Perez', 'PEREZ JUAN', '2', '52', '', '16', '', '', '', '', '', '', 'SENIOR', 'AGOSTO'],
                ['Juan Perez', 'GOMEZ MARIA', '1', '33', '', '', '', '', '', '', '', '', 'ORO', 'SEPTIEMBRE'],
                ['Desconocido', 'LOPEZ CARLOS', '1', '40', '', '', '', '', '', '', '', '', 'PLATA', 'AGOSTO'],
                ['Total', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ],
            'JULIO 2026' => [
                ['', '', '', 'Edades'],
                $this->encabezados(),
                ['Juan Perez', 'PEREZ JUAN', '2', '52', '', '16', '', '', '', '', '', '', 'SENIOR', 'AGOSTO'],
                ['Juan Perez', 'FERNANDEZ ANA', '3', '45', '', '', '', '', '', '', '', '', 'ORO', 'JULIO'],
                ['Juan Perez', 'DIAZ PEDRO', '1', '38', '', '', '', '', '', '', '', '', 'ORO', 'AGOSTO'],
            ],
        ];

        $this->planillaFake($pestanas, $valores);

        $this->artisan('ventas:sync')->assertSuccessful();

        Http::assertSent(fn (Request $r) => str_starts_with((string) $r->url(), 'https://sheets.googleapis.com/v4/spreadsheets/planilla-a')
            && $r->hasHeader('Authorization', 'Bearer token-fake'));

        // 5 altas: 3 de agosto (deduplicando PEREZ JUAN, que aparece en ambas
        // pestañas), 1 de julio y 1 adelanto de septiembre.
        $this->assertDatabaseCount('ventas', 5);
        $this->assertDatabaseHas('ventas', [
            'asesor' => 'Juan Perez',
            'afiliado' => 'PEREZ JUAN',
            'capitas' => 2,
            'plan' => 'SENIOR',
            'mes' => '2026-08',
            'fuente' => 'planilla-a',
            'user_id' => $juan->id,
        ]);
        $this->assertDatabaseHas('ventas', ['afiliado' => 'DIAZ PEDRO', 'mes' => '2026-08']);
        $this->assertDatabaseHas('ventas', ['afiliado' => 'GOMEZ MARIA', 'mes' => '2026-09']);
        $this->assertDatabaseHas('ventas', ['afiliado' => 'FERNANDEZ ANA', 'mes' => '2026-07']);
        $this->assertDatabaseHas('ventas', ['asesor' => 'Desconocido', 'user_id' => null]);
        $this->assertDatabaseMissing('ventas', ['afiliado' => 'Total']);
    }

    public function test_sync_enero_rota_de_anio_en_pestana_de_diciembre(): void
    {
        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        $pestanas = ['DICIEMBRE 2026'];
        $valores = [
            'DICIEMBRE 2026' => [
                ['', '', '', 'Edades'],
                $this->encabezados(),
                ['Ana Diaz', 'ALVAREZ LUIS', '1', '30', '', '', '', '', '', '', '', '', 'ORO', 'ENERO'],
                ['Ana Diaz', 'SUAREZ SOFIA', '1', '28', '', '', '', '', '', '', '', '', 'GO', 'DICIEMBRE'],
            ],
        ];

        $this->planillaFake($pestanas, $valores);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseHas('ventas', ['afiliado' => 'ALVAREZ LUIS', 'mes' => '2027-01']);
        $this->assertDatabaseHas('ventas', ['afiliado' => 'SUAREZ SOFIA', 'mes' => '2026-12']);
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

        $pestanas = ['JULIO 2026'];
        $valores = [
            'JULIO 2026' => [
                ['', '', '', 'Edades'],
                $this->encabezados(),
                ['BLANCO MARIA DE LAS MERCEDES', 'GOMEZ ANA', '1', '40', '', '', '', '', '', '', '', '', 'ORO', 'JULIO'],
            ],
        ];

        $this->planillaFake($pestanas, $valores);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseHas('ventas', [
            'asesor' => 'BLANCO MARIA DE LAS MERCEDES',
            'mes' => '2026-07',
            'user_id' => $vendedor->id,
        ]);
    }

    public function test_sync_sin_planilla_configurada_falla_controlado(): void
    {
        config()->set('services.ventas.spreadsheet_ids', []);

        $this->artisan('ventas:sync')->assertFailed();
    }

    public function test_sync_ignora_pestanas_no_mensuales_y_erratas(): void
    {
        User::factory()->create(['role' => 'seller', 'name' => 'Ana Diaz']);

        $this->configurarFake();
        config()->set('services.ventas.spreadsheet_ids', ['planilla-a']);

        // "ENER0 2026" (cero por O) debe leerse como enero 2026.
        $pestanas = ['ENER0 2026', 'Copia de MAYO 2026', 'Valores_May', 'Hoja 4'];
        $valores = [
            'ENER0 2026' => [
                ['', '', '', 'Edades'],
                $this->encabezados(),
                ['Ana Diaz', 'ROMERO JORGE', '1', '35', '', '', '', '', '', '', '', '', 'ORO', 'ENERO'],
            ],
        ];

        $this->planillaFake($pestanas, $valores);

        $this->artisan('ventas:sync')->assertSuccessful();

        $this->assertDatabaseCount('ventas', 1);
        $this->assertDatabaseHas('ventas', ['afiliado' => 'ROMERO JORGE', 'mes' => '2026-01']);
    }
}
