<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Goal;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function getUserScope(Request $request): array
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return [];
        } elseif ($user->isSupervisor()) {
            return collect([$user->id])->merge($user->sellers->pluck('id'))->toArray();
        } else {
            return [$user->id];
        }
    }

    public function index(Request $request): JsonResponse
    {
        $userIds = $this->getUserScope($request);
        $isAdmin = $request->user()->isAdmin();

        $cq = Contact::query();
        $tq = Task::query();
        $convq = Conversation::query();

        if (!$isAdmin) {
            $cq->whereIn('assigned_to', $userIds);
            $tq->whereIn('assigned_to', $userIds);
            $convq->whereIn('assigned_to', $userIds);
        }

        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $contactsTotal = (clone $cq)->count();
        $contactsToday = (clone $cq)->whereDate('created_at', $today)->count();
        $contactsWeek = (clone $cq)->where('created_at', '>=', $weekStart)->count();
        $contactsMonth = (clone $cq)->where('created_at', '>=', $monthStart)->count();

        $totalDealValue = (clone $cq)->sum('deal_value');
        $avgDealValue = (clone $cq)->whereNotNull('deal_value')->avg('deal_value');

        $wonStage = PipelineStage::where('name', 'like', '%Ganado%')->first();
        $lostStage = PipelineStage::where('name', 'like', '%Perdido%')->first();

        $closedWon = $wonStage ? (clone $cq)->where('pipeline_stage_id', $wonStage->id)->count() : 0;
        $closedLost = $lostStage ? (clone $cq)->where('pipeline_stage_id', $lostStage->id)->count() : 0;
        $totalClosed = $closedWon + $closedLost;
        $winRate = $totalClosed > 0 ? round(($closedWon / $totalClosed) * 100, 1) : 0;

        $openConversations = (clone $convq)->where('status', 'open')->count();
        $pendingTasks = (clone $tq)->whereIn('status', ['pending', 'in_progress'])->count();

        $callsQuery = CallLog::query();
        if (!$isAdmin) {
            $callsQuery->whereIn('user_id', $userIds);
        }
        $totalCalls = (clone $callsQuery)->count();
        $callsToday = (clone $callsQuery)->whereDate('created_at', $today)->count();
        $missedCalls = (clone $callsQuery)->where('status', 'missed')->count();

        $followUpTasks = (clone $tq)->where('type', 'follow_up')->count();
        $followUpsToday = (clone $tq)->where('type', 'follow_up')->whereDate('created_at', $today)->count();

        // Contacts over time (last 30 days)
        $contactsOverTime = (clone $cq)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $contactsLast30Days = collect(range(29, 0))->map(function ($i) use ($contactsOverTime) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            return [
                'date' => $date,
                'total' => $contactsOverTime[$date]->total ?? 0,
            ];
        });

        // Contacts by pipeline stage
        $contactsByStage = (clone $cq)
            ->selectRaw('pipeline_stage_id, count(*) as total')
            ->whereNotNull('pipeline_stage_id')
            ->groupBy('pipeline_stage_id')
            ->with('pipelineStage')
            ->get();

        // Contacts by source
        $contactsBySource = (clone $cq)
            ->selectRaw('COALESCE(source, "directo") as source, count(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        // Deals by seller (admin/supervisor only)
        $salesBySeller = collect();
        if (!$isAdmin || $request->user()->isAdmin()) {
            $sellerIds = $isAdmin ? User::where('role', 'seller')->pluck('id') : collect($userIds);
            $salesBySeller = $sellerIds->map(function ($id) use ($wonStage) {
                $user = User::find($id);
                if (!$user) return null;
                $total = Contact::where('assigned_to', $id)->count();
                $won = $wonStage ? Contact::where('assigned_to', $id)->where('pipeline_stage_id', $wonStage->id)->count() : 0;
                $dealValue = Contact::where('assigned_to', $id)->sum('deal_value');
                $followUps = Task::where('assigned_to', $id)->where('type', 'follow_up')->count();
                return [
                    'user_id' => $id,
                    'name' => $user->name,
                    'total_contacts' => $total,
                    'closed_won' => $won,
                    'deal_value' => $dealValue,
                    'follow_ups' => $followUps,
                ];
            })->filter()->values();
        }

        // Follow-ups by day (last 14 days)
        $followUpsByDay = (clone $tq)
            ->where('type', 'follow_up')
            ->where('created_at', '>=', Carbon::now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $followUpsLast14Days = collect(range(13, 0))->map(function ($i) use ($followUpsByDay) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            return [
                'date' => $date,
                'total' => $followUpsByDay[$date]->total ?? 0,
            ];
        });

        // Calls by day (last 14 days)
        $callsByDay = (clone $callsQuery)
            ->where('created_at', '>=', Carbon::now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $callsLast14Days = collect(range(13, 0))->map(function ($i) use ($callsByDay) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            return [
                'date' => $date,
                'total' => $callsByDay[$date]->total ?? 0,
            ];
        });

        // Conversion funnel
        $stages = PipelineStage::orderBy('order')->get();
        $funnel = $stages->map(function ($stage) use ($cq) {
            $count = (clone $cq)->where('pipeline_stage_id', $stage->id)->count();
            return [
                'stage' => $stage->name,
                'color' => $stage->color,
                'count' => $count,
            ];
        });

        // Goals progress
        $goals = Goal::with('user')
            ->where(function ($q) use ($userIds, $isAdmin) {
                if (!$isAdmin) $q->whereIn('user_id', $userIds);
            })
            ->where('end_date', '>=', $weekStart)
            ->get();

        // Tasks by status
        $tasksByStatus = [
            'pending' => (clone $tq)->where('status', 'pending')->count(),
            'in_progress' => (clone $tq)->where('status', 'in_progress')->count(),
            'completed' => (clone $tq)->where('status', 'completed')->count(),
        ];

        // Recent activity
        $recentContacts = (clone $cq)
            ->with(['assignedTo', 'pipelineStage'])
            ->latest()
            ->limit(5)
            ->get();

        $upcomingTasks = (clone $tq)
            ->with('contact')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'total_contacts' => $contactsTotal,
                'new_contacts_today' => $contactsToday,
                'new_contacts_week' => $contactsWeek,
                'new_contacts_month' => $contactsMonth,
                'total_deal_value' => $totalDealValue,
                'avg_deal_value' => round($avgDealValue ?? 0, 2),
                'closed_won' => $closedWon,
                'closed_lost' => $closedLost,
                'win_rate' => $winRate,
                'open_conversations' => $openConversations,
                'pending_tasks' => $pendingTasks,
                'total_calls' => $totalCalls,
                'calls_today' => $callsToday,
                'missed_calls' => $missedCalls,
                'total_follow_ups' => $followUpTasks,
                'follow_ups_today' => $followUpsToday,
            ],
            'contacts_over_time' => $contactsLast30Days,
            'contacts_by_stage' => $contactsByStage,
            'contacts_by_source' => $contactsBySource,
            'sales_by_seller' => $salesBySeller,
            'follow_ups_by_day' => $followUpsLast14Days,
            'calls_by_day' => $callsLast14Days,
            'conversion_funnel' => $funnel,
            'tasks_by_status' => $tasksByStatus,
            'goals' => $goals,
            'recent_contacts' => $recentContacts,
            'upcoming_tasks' => $upcomingTasks,
        ]);
    }
}
