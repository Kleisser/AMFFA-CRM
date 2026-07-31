<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Goal::with('user');

        if ($request->user()->isSupervisor()) {
            $query->where(function ($q) use ($request) {
                $q->where('created_by', $request->user()->id)
                  ->orWhereHas('user', function ($uq) use ($request) {
                      $uq->where('supervisor_id', $request->user()->id);
                  });
            });
        } elseif ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        }

        return response()->json($query->orderBy('start_date', 'desc')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:contacts,follow_ups,sales,calls,visits',
            'target' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $validated['created_by'] = $request->user()->id;

        $goal = Goal::create($validated);

        return response()->json($goal->load('user'), 201);
    }

    public function update(Request $request, Goal $goal): JsonResponse
    {
        $validated = $request->validate([
            'target' => 'sometimes|integer|min:1',
            'progress' => 'sometimes|integer|min:0',
        ]);

        $goal->update($validated);

        return response()->json($goal->load('user'));
    }

    public function destroy(Goal $goal): JsonResponse
    {
        $goal->delete();
        return response()->json(['message' => 'Goal deleted']);
    }
}
