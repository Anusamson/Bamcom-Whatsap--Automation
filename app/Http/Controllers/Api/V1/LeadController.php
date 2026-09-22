<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Lead\CreateLeadDTO;
use App\DTOs\Lead\UpdateLeadDTO;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\CreateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Models\Lead;
use App\Services\Lead\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService
    ) {}

    /**
     * List sales opportunities with multifaceted filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->only([
            'status',
            'temperature',
            'agent',
            'source',
            'property',
            'date',
            'date_from',
            'date_to',
            'contact_id',
            'search',
            'per_page',
        ]);

        $paginator = $this->leadService->getPaginatedLeads($filters);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'metrics' => $this->leadService->getLeadMetrics(),
        ]);
    }

    /**
     * Store a newly created sales opportunity.
     */
    public function store(CreateLeadRequest $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $dto = CreateLeadDTO::fromArray($request->validated());
        $lead = $this->leadService->createLead($dto);

        return response()->json([
            'message' => 'Sales opportunity created successfully.',
            'data' => $lead,
        ], 201);
    }

    /**
     * Display the specified lead.
     */
    public function show(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        $lead->load(['contact', 'assignedUser.profile', 'assignedUser.team']);

        return response()->json([
            'data' => $lead,
        ]);
    }

    /**
     * Update the specified lead.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $dto = UpdateLeadDTO::fromArray($request->validated());
        $updated = $this->leadService->updateLead($lead, $dto);

        return response()->json([
            'message' => 'Sales opportunity updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Remove the specified lead.
     */
    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $this->leadService->deleteLead($lead);

        return response()->json([
            'message' => 'Sales opportunity archived successfully.',
        ]);
    }

    /**
     * Quick status update endpoint.
     */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', LeadStatus::values())],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $status = LeadStatus::from($validated['status']);
        $updated = $this->leadService->updateStatus($lead, $status, $validated['lost_reason'] ?? null);

        return response()->json([
            'message' => 'Lead status updated to '.$status->label().'.',
            'data' => $updated,
        ]);
    }

    /**
     * Quick representative assignment endpoint.
     */
    public function assign(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('assign', $lead);

        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $updated = $this->leadService->assignRepresentative($lead, $validated['assigned_user_id'] ?? null);

        return response()->json([
            'message' => 'Lead assigned to representative.',
            'data' => $updated,
        ]);
    }
}
