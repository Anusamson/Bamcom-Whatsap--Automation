<?php

namespace App\Http\Controllers;

use App\Enums\LeadTemperature;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Lead\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineService $pipelineService
    ) {}

    /**
     * Display the Kanban Sales Pipeline Board.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $pipelineIdentifier = $request->query('pipeline');
        $pipeline = $pipelineIdentifier ? $this->pipelineService->findPipeline($pipelineIdentifier) : null;

        $filters = $request->only([
            'agent',
            'assigned_user_id',
            'temperature',
            'search',
            'date',
            'date_from',
            'from_date',
            'date_to',
            'to_date',
        ]);

        $boardData = $this->pipelineService->getKanbanBoardData($pipeline, $filters);

        $agents = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $temperatures = array_map(fn (LeadTemperature $temp): array => [
            'value' => $temp->value,
            'label' => $temp->label(),
            'icon' => $temp->icon(),
            'badge' => $temp->badgeClass(),
        ], LeadTemperature::cases());

        return Inertia::render('Pipelines/Kanban', [
            'board' => $boardData,
            'agents' => $agents,
            'temperatures' => $temperatures,
        ]);
    }

    /**
     * Move a lead to a new pipeline stage.
     */
    public function moveStage(Request $request, Lead $lead): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', 'exists:pipeline_stages,id'],
        ]);

        $newStage = PipelineStage::findOrFail($validated['pipeline_stage_id']);
        $updatedLead = $this->pipelineService->moveLeadStage($lead, $newStage, $request->user());

        $message = sprintf('Opportunity "%s" moved to stage %s.', $updatedLead->title, $newStage->name);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'lead' => $updatedLead,
            ]);
        }

        return back()->with('success', $message);
    }
}
