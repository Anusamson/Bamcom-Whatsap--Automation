<?php

namespace App\Http\Controllers;

use App\Enums\LeadSource;
use App\Enums\LeadTemperature;
use App\Models\Audience;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Tag;
use App\Models\User;
use App\Services\Campaign\AudienceSegmentationService;
use App\Services\Campaign\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AudienceController extends Controller
{
    public function __construct(
        protected CampaignService $campaignService,
        protected AudienceSegmentationService $segmentationService
    ) {}

    /**
     * Display a listing of audiences.
     */
    public function index(): Response
    {
        $audiences = Audience::query()
            ->withCount('campaigns')
            ->with('creator:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('Campaigns/Audiences', [
            'audiences' => $audiences,
            'pipelineStages' => PipelineStage::query()->orderBy('order_column')->get(['id', 'name']),
            'temperatures' => array_map(fn (LeadTemperature $t): array => [
                'value' => $t->value,
                'label' => $t->label(),
            ], LeadTemperature::cases()),
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->where('status', 'available')->orderBy('title')->get(['id', 'title']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'leadSources' => array_map(fn (LeadSource $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ], LeadSource::cases()),
        ]);
    }

    /**
     * Store a newly created audience.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'filters' => 'nullable|array',
            'filters.stage_ids' => 'nullable|array',
            'filters.temperatures' => 'nullable|array',
            'filters.tags' => 'nullable|array',
            'filters.locations' => 'nullable|array',
            'filters.property_ids' => 'nullable|array',
            'filters.min_budget' => 'nullable|numeric|min:0',
            'filters.max_budget' => 'nullable|numeric|min:0',
            'filters.agent_ids' => 'nullable|array',
            'filters.sources' => 'nullable|array',
            'filters.inspection_statuses' => 'nullable|array',
            'filters.last_contact_within_days' => 'nullable|integer|min:1',
            'filters.last_contact_before_days' => 'nullable|integer|min:1',
        ]);

        $audience = $this->campaignService->createAudience($validated, $request->user());

        return back()->with('success', "Audience '{$audience->name}' created ({$audience->cached_count} contacts).");
    }

    /**
     * Update an existing audience.
     */
    public function update(Request $request, Audience $audience): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'filters' => 'nullable|array',
        ]);

        $this->campaignService->updateAudience($audience, $validated);

        return back()->with('success', "Audience '{$audience->name}' updated ({$audience->cached_count} contacts).");
    }

    /**
     * Delete audience.
     */
    public function destroy(Audience $audience): RedirectResponse
    {
        $name = $audience->name;
        $this->campaignService->deleteAudience($audience);

        return back()->with('success', "Audience '{$name}' deleted.");
    }

    /**
     * Real-time preview of segmentation count and sample contacts.
     */
    public function preview(Request $request): JsonResponse
    {
        $filters = (array) $request->input('filters', []);
        $preview = $this->segmentationService->previewSegmentation($filters);

        return response()->json($preview);
    }
}
