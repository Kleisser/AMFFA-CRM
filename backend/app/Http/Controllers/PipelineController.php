<?php

namespace App\Http\Controllers;

use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Pipeline::with('stages')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stages' => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.color' => 'nullable|string',
        ]);

        $pipeline = Pipeline::create([
            'name' => $validated['name'],
            'created_by' => $request->user()->id,
        ]);

        foreach ($validated['stages'] as $index => $stage) {
            PipelineStage::create([
                'pipeline_id' => $pipeline->id,
                'name' => $stage['name'],
                'order' => $index,
                'color' => $stage['color'] ?? '#6B7280',
            ]);
        }

        return response()->json($pipeline->load('stages'), 201);
    }

    public function update(Request $request, Pipeline $pipeline): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $pipeline->update($validated);

        if ($request->has('stages')) {
            $pipeline->stages()->delete();
            foreach ($request->stages as $index => $stage) {
                PipelineStage::create([
                    'pipeline_id' => $pipeline->id,
                    'name' => $stage['name'],
                    'order' => $index,
                    'color' => $stage['color'] ?? '#6B7280',
                ]);
            }
        }

        return response()->json($pipeline->load('stages'));
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        $pipeline->delete();
        return response()->json(['message' => 'Pipeline deleted']);
    }
}
