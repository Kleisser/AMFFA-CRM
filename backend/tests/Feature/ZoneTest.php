<?php

namespace Tests\Feature;

use App\Models\Locality;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
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

    public function test_authenticated_users_can_list_zones(): void
    {
        $zone = Zone::create(['name' => 'ZONA SUR']);
        Locality::create(['zone_id' => $zone->id, 'name' => 'BURZACO', 'partido' => 'ALMIRANTE BROWN', 'code' => '1852']);

        $this->actingAs($this->seller(), 'sanctum')
            ->getJson('/api/zones')
            ->assertOk()
            ->assertJsonFragment(['name' => 'ZONA SUR', 'localities_count' => 1]);
    }

    public function test_only_admin_can_create_zone(): void
    {
        $this->actingAs($this->seller(), 'sanctum')
            ->postJson('/api/zones', ['name' => 'ZONA NUEVA'])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/zones', ['name' => 'ZONA NUEVA'])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'ZONA NUEVA']);

        $this->assertDatabaseHas('zones', ['name' => 'ZONA NUEVA']);
    }

    public function test_only_admin_can_update_and_delete_zone(): void
    {
        $zone = Zone::create(['name' => 'ZONA A']);

        $this->actingAs($this->seller(), 'sanctum')
            ->putJson("/api/zones/{$zone->id}", ['name' => 'ZONA B'])
            ->assertForbidden();

        $this->actingAs($this->seller(), 'sanctum')
            ->deleteJson("/api/zones/{$zone->id}")
            ->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/zones/{$zone->id}", ['name' => 'ZONA B'])
            ->assertOk();

        $this->assertDatabaseHas('zones', ['name' => 'ZONA B']);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/zones/{$zone->id}")
            ->assertOk();

        $this->assertDatabaseMissing('zones', ['name' => 'ZONA B']);
    }

    public function test_zone_with_contacts_cannot_be_deleted(): void
    {
        $zone = Zone::create(['name' => 'ZONA CON CONTACTOS']);
        $seller = $this->seller();

        \App\Models\Contact::create([
            'name' => 'Cliente',
            'dni' => '12345677',
            'created_by' => $seller->id,
            'assigned_to' => $seller->id,
            'zone_id' => $zone->id,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/zones/{$zone->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('zones', ['name' => 'ZONA CON CONTACTOS']);
    }

    public function test_localities_search_filters_by_name_and_partido(): void
    {
        $zone = Zone::create(['name' => 'ZONA SUR']);
        Locality::create(['zone_id' => $zone->id, 'name' => 'BURZACO', 'partido' => 'ALMIRANTE BROWN']);
        Locality::create(['zone_id' => $zone->id, 'name' => 'GLEW', 'partido' => 'ALMIRANTE BROWN']);

        $this->actingAs($this->seller(), 'sanctum')
            ->getJson('/api/localities?search=glew')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'GLEW']);
    }

    public function test_only_admin_can_create_locality(): void
    {
        $zone = Zone::create(['name' => 'ZONA SUR']);

        $this->actingAs($this->seller(), 'sanctum')
            ->postJson("/api/zones/{$zone->id}/localities", ['name' => 'BURZACO'])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/zones/{$zone->id}/localities", ['name' => 'BURZACO'])
            ->assertCreated();

        $this->assertDatabaseHas('localities', ['zone_id' => $zone->id, 'name' => 'BURZACO']);
    }

    public function test_contact_creation_auto_resolves_zone_from_locality(): void
    {
        $zone = Zone::create(['name' => 'LA PLATA']);
        $locality = Locality::create(['zone_id' => $zone->id, 'name' => 'CAÑUELAS']);
        $seller = $this->seller();

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson('/api/contacts', [
                'name' => 'Cliente Zona',
                'dni' => '12345679',
                'locality_id' => $locality->id,
            ])
            ->assertCreated();

        $this->assertEquals($zone->id, $response->json('zone_id'));
        $this->assertDatabaseHas('contacts', ['id' => $response->json('id'), 'zone_id' => $zone->id]);
    }
}
