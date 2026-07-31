<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ActivityLog;
use App\Models\PipelineStage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contact::with(['assignedTo', 'pipelineStage', 'createdBy']);

        if ($request->user()->isSeller()) {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->user()->isSupervisor()) {
            $query->whereHas('assignedTo', function ($q) use ($request) {
                $q->where('supervisor_id', $request->user()->id)
                  ->orWhere('id', $request->user()->id);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($request->has('stage_id')) {
            $query->where('pipeline_stage_id', $request->stage_id);
        }

        if ($request->has('archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        } else {
            $query->where('is_archived', false);
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = $request->get('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'custom_fields' => 'nullable|array',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'deal_value' => 'nullable|numeric',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $validated['created_by'] = $request->user()->id;

        if (!isset($validated['assigned_to'])) {
            $validated['assigned_to'] = $request->user()->id;
        }

        $contact = Contact::create($validated);
        $this->calculateLeadScore($contact);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'contact_id' => $contact->id,
            'action' => 'created',
            'description' => "Contacto {$contact->name} creado",
        ]);

        return response()->json($contact->load(['assignedTo', 'pipelineStage']), 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        return response()->json($contact->load([
            'assignedTo',
            'pipelineStage',
            'createdBy',
            'conversations.assignedTo',
            'tasks',
            'reminders',
            'visits',
            'notes.user',
            'callLogs',
            'products',
        ]));
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'source' => 'nullable|string|max:100',
            'custom_fields' => 'nullable|array',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'deal_value' => 'nullable|numeric',
            'expected_close_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
            'is_archived' => 'nullable|boolean',
        ]);

        $contact->update($validated);
        $this->calculateLeadScore($contact);

        return response()->json($contact->load(['assignedTo', 'pipelineStage']));
    }

    public function updateStage(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate(['pipeline_stage_id' => 'required|exists:pipeline_stages,id']);
        $oldStage = $contact->pipelineStage?->name ?? 'unknown';
        $contact->update($validated);
        $newStage = $contact->pipelineStage?->name ?? 'unknown';

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'contact_id' => $contact->id,
            'action' => 'stage_changed',
            'description' => "{$contact->name} movido de {$oldStage} a {$newStage}",
        ]);

        return response()->json($contact->load('pipelineStage'));
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();
        return response()->json(['message' => 'Contact deleted']);
    }

    private function calculateLeadScore(Contact $contact): void
    {
        $score = 0;
        if ($contact->email) $score += 5;
        if ($contact->phone) $score += 5;
        if ($contact->company) $score += 5;
        if ($contact->deal_value && $contact->deal_value > 0) $score += 10;

        $sourceScores = ['referral' => 10, 'website' => 5, 'facebook' => 3, 'instagram' => 3, 'call' => 3, 'whatsapp' => 4];
        $score += $sourceScores[$contact->source] ?? 1;

        if ($contact->pipelineStage) {
            $stageName = $contact->pipelineStage->name;
            if (str_contains($stageName, 'Negociación')) $score += 15;
            elseif (str_contains($stageName, 'Propuesta')) $score += 12;
            elseif (str_contains($stageName, 'Contactado')) $score += 8;
        }

        $contact->conversations()->count() > 0 ? $score += 10 : null;
        $contact->tasks()->count() > 0 ? $score += 5 : null;

        if ($contact->last_activity_at && Carbon::parse($contact->last_activity_at)->gt(Carbon::now()->subDays(7))) {
            $score += 10;
        }

        $contact->updateQuietly(['lead_score' => min($score, 100)]);
    }
}
