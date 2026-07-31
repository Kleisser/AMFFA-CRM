<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\PipelineStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForecastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        return response()->json(Cache::remember($cacheKey, 120, function () use ($user, $isAdmin) {

        $query = Contact::query()->with('pipelineStage');
        if (!$isAdmin) {
            if ($user->isSupervisor()) {
                $ids = collect([$user->id])->merge($user->sellers->pluck('id'))->toArray();
                $query->whereIn('assigned_to', $ids);
            } else {
                $query->where('assigned_to', $user->id);
            }
        }

        $stages = PipelineStage::orderBy('order')->get();
        $stageProbabilities = [
            $stages[0]->id ?? 0 => 0.1,
            $stages[1]->id ?? 0 => 0.2,
            $stages[2]->id ?? 0 => 0.4,
            $stages[3]->id ?? 0 => 0.6,
            $stages[4]->id ?? 0 => 1.0,
            $stages[5]->id ?? 0 => 0.0,
        ];

        $totalPipeline = (clone $query)->whereNotNull('deal_value')->where('deal_value', '>', 0)->sum('deal_value');

        $weightedPipeline = 0;
        foreach ($stages as $stage) {
            $stageTotal = (clone $query)->where('pipeline_stage_id', $stage->id)->sum('deal_value');
            $prob = $stageProbabilities[$stage->id] ?? 0;
            $weightedPipeline += $stageTotal * $prob;
        }

        $wonStage = $stages->first(fn($s) => str_contains($s->name, 'Ganado'));
        $wonAmount = $wonStage ? (clone $query)->where('pipeline_stage_id', $wonStage->id)->sum('deal_value') : 0;

        $monthlyForecast = collect(range(0, 5))->map(function ($i) use ($query) {
            $start = Carbon::now()->startOfMonth()->addMonths($i);
            $end = (clone $start)->endOfMonth();
            $amount = (clone $query)
                ->whereNotNull('expected_close_date')
                ->whereBetween('expected_close_date', [$start, $end])
                ->sum('deal_value');
            return [
                'month' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'amount' => round($amount, 2),
            ];
        });

        $bySeller = collect();
        if ($isAdmin) {
            $sellerIds = User::where('role', 'seller')->pluck('id');
            $bySeller = $sellerIds->map(function ($id) use ($stageProbabilities, $stages) {
                $user = User::find($id);
                if (!$user) return null;
                $q = Contact::where('assigned_to', $id);
                $total = (clone $q)->sum('deal_value');
                $weighted = 0;
                foreach ($stages as $stage) {
                    $stageTotal = (clone $q)->where('pipeline_stage_id', $stage->id)->sum('deal_value');
                    $weighted += $stageTotal * ($stageProbabilities[$stage->id] ?? 0);
                }
                return [
                    'user_id' => $id,
                    'name' => $user->name,
                    'pipeline_value' => round($total, 2),
                    'weighted_forecast' => round($weighted, 2),
                ];
            })->filter()->values();
        }

        return [
            'total_pipeline_value' => round($totalPipeline, 2),
            'weighted_forecast' => round($weightedPipeline, 2),
            'won_amount' => round($wonAmount, 2),
            'win_rate' => 0,
            'monthly_forecast' => $monthlyForecast,
            'by_seller' => $bySeller,
            'stages' => $stages->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'probability' => ($stageProbabilities[$s->id] ?? 0) * 100,
                'amount' => round((clone $query)->where('pipeline_stage_id', $s->id)->sum('deal_value'), 2),
                'count' => (clone $query)->where('pipeline_stage_id', $s->id)->count(),
            ]),
        ];
        }));
    }
}
