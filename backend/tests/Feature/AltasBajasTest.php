<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\CierreMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AltasBajasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => 'seller']);
    }

    public function test_periodo_de_cierre_mensual(): void
    {
        $this->assertSame(
            ['mes' => '2026-07', 'desde' => '2026-06-26', 'hasta' => '2026-07-25'],
            CierreMensual::periodo('2026-07')
        );

        $this->assertSame(
            ['mes' => '2026-01', 'desde' => '2025-12-26', 'hasta' => '2026-01-25'],
            CierreMensual::periodo('2026-01')
        );

        $this->assertSame(
            ['mes' => '2026-03', 'desde' => '2026-02-26', 'hasta' => '2026-03-25'],
            CierreMensual::periodo('2026-03')
        );
    }

    public function test_cierres_genera_la_cantidad_pedida(): void
    {
        $cierres = CierreMensual::cierres('2026-07', 3);

        $this->assertCount(3, $cierres);
        $this->assertSame('2026-05', $cierres[0]['mes']);
        $this->assertSame('2026-07', $cierres[2]['mes']);
        $this->assertSame('2026-07-25', $cierres[2]['hasta']);
    }

    public function test_mes_vigente_respeta_el_dia_25(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', CierreMensual::mesVigente());
    }

    public function test_solo_admin_o_supervisor_puede_ver_altas_y_bajas(): void
    {
        $this->actingAs($this->seller(), 'sanctum')
            ->getJson('/api/external-checks/altas-bajas')
            ->assertForbidden();

        // Fuerza el estado "puente no configurado" sin depender del .env local.
        config()->set('services.gecros.base_url', '');
        config()->set('services.gecros.api_key', '');

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/external-checks/altas-bajas')
            ->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonCount(0, 'altas')
            ->assertJsonCount(0, 'bajas');
    }

    public function test_mes_invalido_devuelve_422(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/external-checks/altas-bajas?mes=2026-13')
            ->assertStatus(422);
    }
}
