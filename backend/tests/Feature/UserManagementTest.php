<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    public function test_admin_can_reassign_seller_to_another_supervisor(): void
    {
        $admin = $this->userWithRole('admin');
        $sup1 = $this->userWithRole('supervisor');
        $sup2 = $this->userWithRole('supervisor');
        $seller = $this->userWithRole('seller', ['supervisor_id' => $sup1->id]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/supervisor", ['supervisor_id' => $sup2->id])
            ->assertOk()
            ->assertJsonFragment(['id' => $seller->id, 'supervisor_id' => $sup2->id]);

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'supervisor_id' => $sup2->id]);
    }

    public function test_admin_can_unassign_seller_with_null_supervisor(): void
    {
        $admin = $this->userWithRole('admin');
        $sup1 = $this->userWithRole('supervisor');
        $seller = $this->userWithRole('seller', ['supervisor_id' => $sup1->id]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/supervisor", ['supervisor_id' => null])
            ->assertOk()
            ->assertJsonFragment(['id' => $seller->id, 'supervisor_id' => null]);

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'supervisor_id' => null]);
    }

    public function test_cannot_assign_supervisor_to_non_supervisor_user(): void
    {
        $admin = $this->userWithRole('admin');
        $sellerAsTarget = $this->userWithRole('seller');
        $seller = $this->userWithRole('seller');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/supervisor", ['supervisor_id' => $sellerAsTarget->id])
            ->assertUnprocessable();

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'supervisor_id' => null]);
    }

    public function test_cannot_reassign_non_seller_user(): void
    {
        $admin = $this->userWithRole('admin');
        $sup = $this->userWithRole('supervisor');
        $otherSup = $this->userWithRole('supervisor');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$sup->id}/supervisor", ['supervisor_id' => $otherSup->id])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_reassign_seller(): void
    {
        $sup = $this->userWithRole('supervisor');
        $sup2 = $this->userWithRole('supervisor');
        $seller = $this->userWithRole('seller', ['supervisor_id' => $sup->id]);

        $this->actingAs($sup, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/supervisor", ['supervisor_id' => $sup2->id])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'supervisor_id' => $sup->id]);
    }

    public function test_admin_can_deactivate_seller(): void
    {
        $admin = $this->userWithRole('admin');
        $seller = $this->userWithRole('seller');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/active", ['is_active' => false])
            ->assertOk()
            ->assertJsonFragment(['id' => $seller->id, 'is_active' => false]);

        $this->assertDatabaseHas('users', ['id' => $seller->id, 'is_active' => false]);
    }

    public function test_admin_can_reactivate_seller(): void
    {
        $admin = $this->userWithRole('admin');
        $seller = $this->userWithRole('seller', ['is_active' => false]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/active", ['is_active' => true])
            ->assertOk()
            ->assertJsonFragment(['id' => $seller->id, 'is_active' => true]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$admin->id}/active", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_cannot_deactivate_supervisor_or_admin(): void
    {
        $admin = $this->userWithRole('admin');
        $sup = $this->userWithRole('supervisor');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$sup->id}/active", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_deactivate_seller(): void
    {
        $sup = $this->userWithRole('supervisor');
        $seller = $this->userWithRole('seller');

        $this->actingAs($sup, 'sanctum')
            ->patchJson("/api/users/{$seller->id}/active", ['is_active' => false])
            ->assertForbidden();
    }

    public function test_only_admin_can_update_or_delete_users(): void
    {
        $sup = $this->userWithRole('supervisor');
        $seller = $this->userWithRole('seller');

        $this->actingAs($sup, 'sanctum')
            ->putJson("/api/users/{$seller->id}", ['name' => 'Nuevo nombre'])
            ->assertForbidden();

        $this->actingAs($sup, 'sanctum')
            ->deleteJson("/api/users/{$seller->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $seller->id]);
    }

    public function test_admin_can_update_seller(): void
    {
        $admin = $this->userWithRole('admin');
        $seller = $this->userWithRole('seller');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$seller->id}", ['name' => 'Nuevo nombre'])
            ->assertOk()
            ->assertJsonFragment(['id' => $seller->id, 'name' => 'Nuevo nombre']);
    }

    public function test_admin_cannot_deactivate_self_via_update(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422);
    }

    public function test_deactivated_seller_is_rejected_on_login(): void
    {
        $seller = $this->userWithRole('seller', ['is_active' => false, 'email' => 'baja@amffa.com.ar']);

        $this->postJson('/api/auth/login', [
            'email' => $seller->email,
            'password' => 'password',
        ])->assertForbidden();
    }
}
