<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VentasControllerTest extends TestCase
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

    public function test_kpi_de_ventas_agrupa_por_vendedor_y_equipo(): void
    {
        $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Anzelmo Ignacio']);
        $v1 = User::factory()->create(['role' => 'seller', 'name' => 'Juan Perez', 'supervisor_id' => $sup->id]);
        $v2 = User::factory()->create(['role' => 'seller', 'name' => 'Ana Diaz', 'supervisor_id' => $sup->id]);
        $sinEquipo = User::factory()->create(['role' => 'seller', 'name' => 'Suplente']);

        Venta::create(['asesor' => 'Juan Perez', 'user_id' => $v1->id, 'afiliado' => 'A1', 'capitas' => 2, 'mes' => '2026-07']);
        Venta::create(['asesor' => 'Juan Perez', 'user_id' => $v1->id, 'afiliado' => 'A2', 'capitas' => 1, 'mes' => '2026-07']);
        Venta::create(['asesor' => 'Ana Diaz', 'user_id' => $v2->id, 'afiliado' => 'B1', 'capitas' => 3, 'mes' => '2026-07']);
        Venta::create(['asesor' => 'Juan Perez', 'user_id' => $v1->id, 'afiliado' => 'A3', 'capitas' => 1, 'mes' => '2026-08']);
        Venta::create(['asesor' => 'Suplente', 'user_id' => $sinEquipo->id, 'afiliado' => 'C1', 'capitas' => 1, 'mes' => '2026-07']);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/ventas?mes=2026-07')
            ->assertOk()
            ->assertJsonPath('mes', '2026-07')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('capitas', 7)
            ->assertJsonCount(3, 'por_vendedor')
            ->assertJsonPath('por_vendedor.0.asesor', 'Juan Perez')
            ->assertJsonPath('por_vendedor.0.altas', 2)
            ->assertJsonPath('por_vendedor.0.capitas', 3)
            ->assertJsonPath('por_equipo.0.equipo', 'Anzelmo Ignacio')
            ->assertJsonPath('por_equipo.0.altas', 3)
            ->assertJsonPath('por_equipo.0.capitas', 6)
            ->assertJsonCount(2, 'por_equipo');
    }

    public function test_ventas_solo_admin_o_supervisor(): void
    {
        $this->actingAs($this->seller(), 'sanctum')
            ->getJson('/api/ventas')
            ->assertForbidden();
    }

    public function test_mes_invalido_devuelve_422(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/ventas?mes=2026-13')
            ->assertStatus(422);
    }

    public function test_mes_sin_datos_devuelve_vacio(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/ventas?mes=2026-01')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'por_vendedor');
    }
}
