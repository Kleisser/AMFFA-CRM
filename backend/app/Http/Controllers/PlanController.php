<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanIncrease;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Plan::with('createdBy')
            ->with(['prices' => fn ($q) => $q->orderByDesc('period')->limit(1)])
            ->where('is_active', true);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('name')->paginate($request->get('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $validated['created_by'] = $request->user()->id;

        $plan = Plan::create($validated);

        return response()->json($plan->load('createdBy'), 201);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan->load(['createdBy', 'prices' => fn ($q) => $q->orderByDesc('period')]));
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan->update($validated);

        return response()->json($plan->load('createdBy'));
    }

    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizeAdmin($request);

        $plan->update(['is_active' => false]);

        return response()->json(['message' => 'Plan archivado']);
    }

    public function prices(Plan $plan): JsonResponse
    {
        return response()->json($plan->prices()->orderByDesc('period')->with('createdBy')->get());
    }

    public function storePrice(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'period' => 'required|string|max:7|regex:/^\d{4}-\d{2}$/',
            'structure' => 'required|array',
            'override' => 'sometimes|boolean',
        ]);

        $exists = $plan->prices()->where('period', $validated['period'])->exists();
        if ($exists && empty($validated['override'])) {
            return response()->json(['message' => 'Ya existe un precio para ese período. Usá override para reemplazarlo.'], 422);
        }

        $price = $plan->prices()->updateOrCreate(
            ['period' => $validated['period']],
            [
                'structure' => $validated['structure'],
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json($price->load('createdBy'), 201);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'period' => 'nullable|string|max:7',
            'titular_age' => 'nullable|integer|min:0|max:110',
            'conyuge_age' => 'nullable|integer|min:0|max:110',
            'child_ages' => 'nullable|array',
            'child_ages.*' => 'integer|min:0|max:110',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $result = app(QuoteService::class)->calculate(
            $plan,
            $validated['period'] ?? null,
            $validated['titular_age'] ?? null,
            $validated['conyuge_age'] ?? null,
            $validated['child_ages'] ?? []
        );

        return response()->json($result);
    }

    public function increase(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'percentage' => 'required|numeric|min:0.01|max:100',
            'plan_ids' => 'sometimes|array',
            'plan_ids.*' => 'exists:plans,id',
            'from_period' => 'nullable|string|max:7|regex:/^\d{4}-\d{2}$/',
        ]);

        $query = Plan::query();
        if (!empty($validated['plan_ids'])) {
            $query->whereIn('id', $validated['plan_ids']);
        }
        $plans = $query->where('is_active', true)->get();

        $pct = (float) $validated['percentage'];

        $targetPeriods = [];
        $created = [];
        $sourcePeriod = null;

        DB::transaction(function () use ($plans, $pct, $validated, $request, &$targetPeriods, &$created, &$sourcePeriod) {
            foreach ($plans as $plan) {
                $latest = $plan->prices()->orderByDesc('period')->first();
                if (!$latest) {
                    continue;
                }

                $fromPeriod = $validated['from_period'] ?? $latest->period;
                $source = $plan->prices()->where('period', $fromPeriod)->first()
                    ?? $plan->prices()->orderByDesc('period')->first();
                if (!$source) {
                    continue;
                }
                $sourcePeriod = $sourcePeriod ?? $source->period;

                $toPeriod = $this->nextPeriod($source->period);
                $targetPeriods[] = $toPeriod;

                $newStructure = $this->applyIncreaseToStructure($source->structure, $pct);

                $plan->prices()->updateOrCreate(
                    ['period' => $toPeriod],
                    [
                        'structure' => $newStructure,
                        'created_by' => $request->user()->id,
                    ]
                );
                $created[] = ['plan_id' => $plan->id, 'period' => $toPeriod];
            }
        });

        if (empty($created)) {
            return response()->json(['message' => 'Ningún plan tenía precios para aumentar'], 422);
        }

        $toPeriod = $created[0]['period'];
        PlanIncrease::create([
            'user_id' => $request->user()->id,
            'percentage' => $pct,
            'from_period' => $sourcePeriod ?? $toPeriod,
            'to_period' => $toPeriod,
            'plan_ids' => array_column($created, 'plan_id'),
        ]);

        return response()->json([
            'message' => "Aumento del {$pct}% aplicado a " . count($created) . " planes para {$toPeriod}",
            'plans' => $created,
        ], 201);
    }

    public function increases(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(
            PlanIncrease::with('user')->orderByDesc('created_at')->paginate($request->get('per_page', 25))
        );
    }

    private function applyIncreaseToStructure(array $structure, float $pct): array
    {
        $factor = 1 + ($pct / 100);

        if (!empty($structure['manual'])) {
            $structure['manual_price'] = round((float) ($structure['manual_price'] ?? 0) * $factor, 2);
            return $structure;
        }

        if (isset($structure['adults']) && is_array($structure['adults'])) {
            foreach ($structure['adults'] as &$bracket) {
                $bracket['price'] = round((float) $bracket['price'] * $factor, 2);
            }
            unset($bracket);
        }

        $children = $structure['children'] ?? [];
        if (($children['mode'] ?? '') === 'flat') {
            $children['first'] = round((float) $children['first'] * $factor, 2);
            $children['rest'] = round((float) $children['rest'] * $factor, 2);
        } elseif (($children['mode'] ?? '') === 'age' && isset($children['tiers']) && is_array($children['tiers'])) {
            foreach ($children['tiers'] as &$tier) {
                $tier['first'] = round((float) $tier['first'] * $factor, 2);
                $tier['rest'] = round((float) $tier['rest'] * $factor, 2);
            }
            unset($tier);
        }
        $structure['children'] = $children;

        return $structure;
    }

    private function nextPeriod(string $period): string
    {
        [$year, $month] = array_map('intval', explode('-', $period));
        $month++;
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Solo el administrador puede modificar planes');
    }
}
