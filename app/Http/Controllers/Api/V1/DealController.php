<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Deal\CreateDealRequest;
use App\Http\Requests\Deal\MarkDealLostRequest;
use App\Http\Requests\Deal\UpdateDealRequest;
use App\Models\Deal;
use App\Services\Deal\DealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    public function __construct(
        protected DealService $dealService
    ) {}

    /**
     * Get paginated deals list with metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'status',
            'assigned_user_id',
            'contact_id',
            'property_id',
            'pipeline_stage_id',
            'min_value',
            'max_value',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $deals = $this->dealService->getPaginatedDeals($filters, $perPage);
        $metrics = $this->dealService->getDealMetrics();

        return response()->json([
            'status' => 'success',
            'data' => $deals->items(),
            'metrics' => $metrics,
            'pagination' => [
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
                'per_page' => $deals->perPage(),
                'total' => $deals->total(),
            ],
        ]);
    }

    /**
     * Store a newly created deal.
     */
    public function store(CreateDealRequest $request): JsonResponse
    {
        $deal = $this->dealService->createDeal($request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Opportunity created successfully.',
            'data' => $deal,
        ], 201);
    }

    /**
     * Get single deal details.
     */
    public function show(Deal $deal): JsonResponse
    {
        $deal->load([
            'contact',
            'lead',
            'property.estate',
            'assignedUser',
            'pipeline',
            'stage',
            'activities.user',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $deal,
        ]);
    }

    /**
     * Update an existing deal.
     */
    public function update(UpdateDealRequest $request, Deal $deal): JsonResponse
    {
        $updated = $this->dealService->updateDeal($deal, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Opportunity updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Delete a deal.
     */
    public function destroy(Deal $deal): JsonResponse
    {
        $this->dealService->deleteDeal($deal);

        return response()->json([
            'status' => 'success',
            'message' => 'Opportunity removed successfully.',
        ]);
    }

    /**
     * Transition deal to Closed Won.
     */
    public function markWon(Request $request, Deal $deal): JsonResponse
    {
        $updated = $this->dealService->markWon($deal, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Opportunity marked as Closed Won.',
            'data' => $updated,
        ]);
    }

    /**
     * Transition deal to Closed Lost.
     */
    public function markLost(MarkDealLostRequest $request, Deal $deal): JsonResponse
    {
        $updated = $this->dealService->markLost($deal, $request->validated('lost_reason'), $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Opportunity marked as Closed Lost.',
            'data' => $updated,
        ]);
    }
}
