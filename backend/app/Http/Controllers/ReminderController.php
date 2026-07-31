<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reminder::with('contact')
            ->where('user_id', $request->user()->id);

        if ($request->has('pending')) {
            $query->where('is_notified', false)
                  ->where('remind_at', '<=', now()->addDay());
        }

        return response()->json($query->orderBy('remind_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'nullable|exists:contacts,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'remind_at' => 'required|date',
        ]);

        $validated['user_id'] = $request->user()->id;

        $reminder = Reminder::create($validated);

        return response()->json($reminder->load('contact'), 201);
    }

    public function update(Request $request, Reminder $reminder): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'remind_at' => 'sometimes|date',
        ]);

        $reminder->update($validated);

        return response()->json($reminder->load('contact'));
    }

    public function destroy(Reminder $reminder): JsonResponse
    {
        $reminder->delete();
        return response()->json(['message' => 'Reminder deleted']);
    }
}
