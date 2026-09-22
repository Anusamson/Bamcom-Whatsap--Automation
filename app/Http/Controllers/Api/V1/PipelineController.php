<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Services\Lead\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineService $pipelineService
    ) {}

    /**
     * List all active sales pipelines.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $pipelines = Pipeline::query()
            ->with(['stages'])
            ->withCount('leads')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('order_column')
            ->get();

        return response()->json([
            'data' => $pipelines,
        ]);
    }

    /**
     * Display the specified pipeline with stages and board metrics.
     */
    public function show(Request $request, Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $boardData = $this->pipelineService->getKanbanBoardData($pipeline, $request->all());

        return response()->json([
            'data' => $boardData,
        ]);
    }

    /**
     * Move a lead to a new pipeline stage.
     */
    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $newStage = PipelineStage::findOrFail($validated['pipeline_stage_id']);
        $updatedLead = $this->pipelineService->moveLeadStage($lead, $newStage, $request->user());

        return response()->json([
            'message' => sprintf('Lead moved to stage %s.', $newStage->name),
            'data' => $updatedLead,
        ]);
    }
}
