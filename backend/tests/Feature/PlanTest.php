<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Plan;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
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

    private function balancePlan(): Plan
    {
        return Plan::create([
            'name' => 'BALANCE',
            'created_by' => $this->admin()->id,
            'prices' => [],
        ]);
    }

    public function test_all_roles_can_list_plans(): void
    {
        $admin = $this->admin();
        Plan::create(['name' => 'ORO', 'created_by' => $admin->id]);

        $this->actingAs($this->seller(), 'sanctum')
            ->getJson('/api/plans')
            ->assertOk()
            ->assertJsonFragment(['name' => 'ORO']);
    }

    public function test_only_admin_can_create_plan(): void
    {
        $this->actingAs($this->seller(), 'sanctum')
            ->postJson('/api/plans', ['name' => 'NUEVO PLAN'])
            ->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/plans', ['name' => 'NUEVO PLAN'])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'NUEVO PLAN']);

        $this->assertDatabaseHas('plans', ['name' => 'NUEVO PLAN']);
    }

    public function test_only_admin_can_apply_increase(): void
    {
        $this->actingAs($this->seller(), 'sanctum')
            ->postJson('/api/plans/increase', ['percentage' => 3])
            ->assertForbidden();
    }

    public function test_increase_generates_next_month_prices_and_registry(): void
    {
        $admin = $this->admin();
        $plan = Plan::create(['name' => 'BALANCE', 'created_by' => $admin->id]);

        $plan->prices()->create([
            'period' => '2026-08',
            'structure' => [
                'manual' => false,
                'has_conyuge' => true,
                'adults' => [
                    ['max_age' => 35, 'price' => 138589],
                    ['max_age' => null, 'price' => 321515],
                ],
                'children' => ['mode' => 'flat', 'first' => 61179, 'rest' => 43905],
            ],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/plans/increase', [
                'percentage' => 10,
                'plan_ids' => [$plan->id],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('plan_prices', ['plan_id' => $plan->id, 'period' => '2026-09']);
        $this->assertDatabaseHas('plan_increases', [
            'percentage' => '10.00',
            'from_period' => '2026-08',
            'to_period' => '2026-09',
        ]);

        $newPrice = $plan->prices()->where('period', '2026-09')->first();
        $this->assertEquals(round(138589 * 1.10, 2), $newPrice->structure['adults'][0]['price']);
        $this->assertEquals(round(43905 * 1.10, 2), $newPrice->structure['children']['rest']);
    }

    public function test_quote_matches_excel_formula_balance(): void
    {
        $admin = $this->admin();
        $plan = Plan::create(['name' => 'BALANCE', 'created_by' => $admin->id]);
        $plan->prices()->create([
            'period' => '2026-07',
            'structure' => [
                'manual' => false,
                'has_conyuge' => true,
                'adults' => [
                    ['max_age' => 35, 'price' => 136004.80],
                    ['max_age' => 40, 'price' => 165891.73],
                    ['max_age' => null, 'price' => 315519.87],
                ],
                'children' => ['mode' => 'flat', 'first' => 60038.13, 'rest' => 43086.28],
            ],
            'created_by' => $admin->id,
        ]);

        $result = app(QuoteService::class)->calculate($plan, '2026-07', 32, 32, [13]);
        $this->assertEquals(332047.73, $result['total']);

        $start = Plan::create(['name' => 'START', 'created_by' => $admin->id]);
        $start->prices()->create([
            'period' => '2026-07',
            'structure' => [
                'manual' => false,
                'has_conyuge' => false,
                'adults' => [
                    ['max_age' => 23, 'price' => 132970.33],
                    ['max_age' => 35, 'price' => 170671.39],
                ],
                'children' => ['mode' => 'none'],
            ],
            'created_by' => $admin->id,
        ]);

        $this->assertEquals(170671.39, app(QuoteService::class)->calculate($start, '2026-07', 30, null, [])['total']);
        $overAge = app(QuoteService::class)->calculate($start, '2026-07', 40, null, []);
        $this->assertArrayHasKey('error', $overAge);
        $this->assertEquals(0, $overAge['total']);
    }

    public function test_quote_uses_latest_period_by_default(): void
    {
        $admin = $this->admin();
        $plan = Plan::create(['name' => 'ORO', 'created_by' => $admin->id]);
        $plan->prices()->create([
            'period' => '2026-07',
            'structure' => [
                'manual' => false,
                'has_conyuge' => true,
                'adults' => [['max_age' => 35, 'price' => 213342.04], ['max_age' => null, 'price' => 494935.03]],
                'children' => ['mode' => 'none'],
            ],
            'created_by' => $admin->id,
        ]);
        $plan->prices()->create([
            'period' => '2026-08',
            'structure' => [
                'manual' => false,
                'has_conyuge' => true,
                'adults' => [['max_age' => 35, 'price' => 217396], ['max_age' => null, 'price' => 504339]],
                'children' => ['mode' => 'none'],
            ],
            'created_by' => $admin->id,
        ]);

        $result = app(QuoteService::class)->calculate($plan, null, 35, null, []);
        $this->assertEquals('2026-08', $result['period']);
        $this->assertEquals(217396, $result['total']);
    }

    public function test_contact_saves_family_and_calculates_deal_value(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $plan = Plan::create(['name' => 'BALANCE', 'created_by' => $admin->id]);
        $plan->prices()->create([
            'period' => '2026-08',
            'structure' => [
                'manual' => false,
                'has_conyuge' => true,
                'adults' => [
                    ['max_age' => 35, 'price' => 138589],
                    ['max_age' => 40, 'price' => 169044],
                    ['max_age' => null, 'price' => 321515],
                ],
                'children' => ['mode' => 'flat', 'first' => 61179, 'rest' => 43905],
            ],
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($seller, 'sanctum')
            ->postJson('/api/contacts', [
                'name' => 'Familia Test',
                'dni' => '12345678',
                'plan_id' => $plan->id,
                'family' => [
                    ['relation' => 'titular', 'age' => 34],
                    ['relation' => 'conyuge', 'age' => 32],
                    ['relation' => 'hijo', 'age' => 10],
                ],
            ])
            ->assertCreated();

        $contactId = $response->json('id');
        $this->assertDatabaseHas('family_members', ['contact_id' => $contactId, 'relation' => 'titular']);
        $this->assertDatabaseHas('family_members', ['contact_id' => $contactId, 'relation' => 'hijo']);

        $contact = Contact::find($contactId);
        $this->assertEquals(138589 + 138589 + 61179, (float) $contact->deal_value);
    }

    public function test_manual_plan_uses_manual_price(): void
    {
        $admin = $this->admin();
        $plan = Plan::create(['name' => 'GO', 'created_by' => $admin->id]);
        $plan->prices()->create([
            'period' => '2026-08',
            'structure' => ['manual' => true, 'manual_price' => 150000],
            'created_by' => $admin->id,
        ]);

        $result = app(QuoteService::class)->calculate($plan, '2026-08', 30, null, []);
        $this->assertEquals(150000, $result['total']);
    }
}
