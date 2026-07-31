<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Goal;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->isAdmin()) {
            $supervisors = User::where('role', 'supervisor')
                ->with(['sellers' => fn($q) => $q->where('role', 'seller')->orderBy('name')])
                ->orderBy('name')
                ->get();

            return response()->json([
                'view' => 'hierarchy',
                'supervisors' => $supervisors,
            ]);
        }

        $query = User::query()->with('supervisor');

        if ($authUser->isSupervisor()) {
            $query->where(function ($q) use ($authUser) {
                $q->where('id', $authUser->id)
                  ->orWhere('supervisor_id', $authUser->id);
            });
        } else {
            $query->where('role', 'seller');
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return response()->json([
            'view' => 'flat',
            'users' => $query->orderBy('name')->get(),
        ]);
    }

    public function kpi(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        $isTargetSeller = $user->isSeller();

        if (!$authUser->isAdmin() && $authUser->id !== $user->id) {
            if ($authUser->isSupervisor() && $user->supervisor_id !== $authUser->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            if ($authUser->isSeller()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $cq = Contact::where('assigned_to', $user->id);
        $tq = Task::where('assigned_to', $user->id);
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();

        $wonStage = PipelineStage::where('name', 'like', '%Ganado%')->first();
        $lostStage = PipelineStage::where('name', 'like', '%Perdido%')->first();

        $contactsTotal = (clone $cq)->count();
        $contactsMonth = (clone $cq)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();
        $closedWon = $wonStage ? (clone $cq)->where('pipeline_stage_id', $wonStage->id)->count() : 0;
        $closedLost = $lostStage ? (clone $cq)->where('pipeline_stage_id', $lostStage->id)->count() : 0;
        $totalClosed = $closedWon + $closedLost;
        $winRate = $totalClosed > 0 ? round(($closedWon / $totalClosed) * 100, 1) : 0;
        $dealValue = (clone $cq)->sum('deal_value');
        $pendingTasks = (clone $tq)->whereIn('status', ['pending', 'in_progress'])->count();
        $completedTasks = (clone $tq)->where('status', 'completed')->count();
        $followUps = (clone $tq)->where('type', 'follow_up')->count();
        $callsTotal = CallLog::where('user_id', $user->id)->count();
        $callsToday = CallLog::where('user_id', $user->id)->whereDate('created_at', $today)->count();

        $contactsOverTime = (clone $cq)
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $contactsLast30Days = collect(range(29, 0))->map(function ($i) use ($contactsOverTime) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            return ['date' => $date, 'total' => $contactsOverTime[$date]->total ?? 0];
        });

        $contactsByStage = (clone $cq)
            ->selectRaw('pipeline_stage_id, count(*) as total')
            ->whereNotNull('pipeline_stage_id')
            ->groupBy('pipeline_stage_id')
            ->with('pipelineStage')
            ->get();

        $goals = Goal::where('user_id', $user->id)
            ->where('end_date', '>=', $weekStart)
            ->get();

        return response()->json([
            'user' => $user->load('supervisor'),
            'summary' => [
                'total_contacts' => $contactsTotal,
                'new_contacts_month' => $contactsMonth,
                'closed_won' => $closedWon,
                'closed_lost' => $closedLost,
                'win_rate' => $winRate,
                'deal_value' => $dealValue,
                'pending_tasks' => $pendingTasks,
                'completed_tasks' => $completedTasks,
                'follow_ups' => $followUps,
                'total_calls' => $callsTotal,
                'calls_today' => $callsToday,
            ],
            'contacts_over_time' => $contactsLast30Days,
            'contacts_by_stage' => $contactsByStage,
            'goals' => $goals,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,supervisor,seller',
            'supervisor_id' => 'nullable|exists:users,id',
            'phone' => 'nullable|string',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json($user->load('supervisor'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:admin,supervisor,seller',
            'supervisor_id' => 'nullable|exists:users,id',
            'phone' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load('supervisor'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}
