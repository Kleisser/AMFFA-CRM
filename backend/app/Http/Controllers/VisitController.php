<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Visit::with(['contact', 'user']);

        if ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->user()->isSupervisor()) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('supervisor_id', $request->user()->id)
                  ->orWhere('id', $request->user()->id);
            });
        }

        $query->orderBy('scheduled_at', 'desc');

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'title' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'scheduled_at' => 'required|date',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['user_id'] = $request->user()->id;

        $visit = Visit::create($validated);

        return response()->json($visit->load(['contact', 'user']), 201);
    }

    public function update(Request $request, Visit $visit): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled',
            'scheduled_at' => 'sometimes|date',
            'completed_at' => 'nullable|date',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $visit->update($validated);

        return response()->json($visit->load(['contact', 'user']));
    }

    public function destroy(Visit $visit): JsonResponse
    {
        $visit->delete();
        return response()->json(['message' => 'Visit deleted']);
    }
}
