<?php

namespace App\Http\Controllers;

use App\Enums\DealStatus;
use App\Http\Requests\Deal\CreateDealRequest;
use App\Http\Requests\Deal\MarkDealLostRequest;
use App\Http\Requests\Deal\UpdateDealRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\User;
use App\Services\Deal\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(
        protected DealService $dealService
    ) {}

    /**
     * Display a listing of opportunities and sales deals.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Deal::class);

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

        $deals = $this->dealService->getPaginatedDeals($filters);
        $metrics = $this->dealService->getDealMetrics();

        $users = User::query()
            ->select('id', 'name', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $properties = Property::query()
            ->select('id', 'title', 'plot_size', 'location')
            ->orderBy('title')
            ->get();

        $pipeline = Pipeline::where('is_default', true)->with('stages')->first() ?? Pipeline::with('stages')->first();
        $stages = $pipeline ? $pipeline->stages : [];

        $statuses = array_map(fn (DealStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], DealStatus::cases());

        return Inertia::render('Deals/Index', [
            'deals' => $deals,
            'filters' => $filters,
            'metrics' => $metrics,
            'users' => $users,
            'properties' => $properties,
            'stages' => $stages,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show the form for creating a new opportunity / deal.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Deal::class);

        $contactId = $request->input('contact_id');
        $leadId = $request->input('lead_id');
        $propertyId = $request->input('property_id');

        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone', 'email')
            ->orderBy('first_name')
            ->get();

        $properties = Property::query()
            ->select('id', 'title', 'plot_size', 'location')
            ->with('activePrice')
            ->published()
            ->get();

        $leads = Lead::query()
            ->select('id', 'title', 'contact_id', 'budget_max', 'budget_min')
            ->when($contactId, fn ($q) => $q->where('contact_id', $contactId))
            ->get();

        $users = User::query()
            ->select('id', 'name', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $pipeline = Pipeline::where('is_default', true)->with('stages')->first() ?? Pipeline::with('stages')->first();

        return Inertia::render('Deals/Create', [
            'contacts' => $contacts,
            'properties' => $properties,
            'leads' => $leads,
            'users' => $users,
            'pipeline' => $pipeline,
            'initialContactId' => $contactId ? (int) $contactId : null,
            'initialLeadId' => $leadId ? (int) $leadId : null,
            'initialPropertyId' => $propertyId ? (int) $propertyId : null,
        ]);
    }

    /**
     * Store a newly created opportunity in storage.
     */
    public function store(CreateDealRequest $request): RedirectResponse
    {
        Gate::authorize('create', Deal::class);

        $deal = $this->dealService->createDeal($request->validated(), $request->user());

        return redirect()->route('deals.show', $deal)
            ->with('success', "Opportunity '{$deal->title}' created successfully.");
    }

    /**
     * Display the specified opportunity / deal 360 profile.
     */
    public function show(Deal $deal): Response
    {
        Gate::authorize('view', $deal);

        $deal->load([
            'contact',
            'lead',
            'property.estate',
            'assignedUser',
            'pipeline',
            'stage',
            'activities.user',
        ]);

        return Inertia::render('Deals/Show', [
            'deal' => $deal,
        ]);
    }

    /**
     * Show the form for editing the specified deal.
     */
    public function edit(Deal $deal): Response
    {
        Gate::authorize('update', $deal);

        $deal->load(['contact', 'lead', 'property', 'assignedUser', 'stage', 'pipeline']);

        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone')
            ->orderBy('first_name')
            ->get();

        $properties = Property::query()
            ->select('id', 'title', 'plot_size', 'location')
            ->with('activePrice')
            ->get();

        $leads = Lead::query()
            ->select('id', 'title', 'contact_id')
            ->where('contact_id', $deal->contact_id)
            ->get();

        $users = User::query()
            ->select('id', 'name', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $pipeline = $deal->pipeline ?? (Pipeline::where('is_default', true)->with('stages')->first() ?? Pipeline::with('stages')->first());

        return Inertia::render('Deals/Edit', [
            'deal' => $deal,
            'contacts' => $contacts,
            'properties' => $properties,
            'leads' => $leads,
            'users' => $users,
            'pipeline' => $pipeline,
        ]);
    }

    /**
     * Update the specified deal in storage.
     */
    public function update(UpdateDealRequest $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $updated = $this->dealService->updateDeal($deal, $request->validated(), $request->user());

        return redirect()->route('deals.show', $updated)
            ->with('success', "Opportunity '{$updated->title}' updated successfully.");
    }

    /**
     * Remove the specified deal from storage.
     */
    public function destroy(Deal $deal): RedirectResponse
    {
        Gate::authorize('delete', $deal);

        $title = $deal->title;
        $this->dealService->deleteDeal($deal);

        return redirect()->route('deals.index')
            ->with('success', "Opportunity '{$title}' removed successfully.");
    }

    /**
     * Transition opportunity status to Closed Won.
     */
    public function markWon(Request $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $this->dealService->markWon($deal, $request->user());

        return back()->with('success', "Opportunity '{$deal->title}' marked as CLOSED WON! 🎉");
    }

    /**
     * Transition opportunity status to Closed Lost with reason.
     */
    public function markLost(MarkDealLostRequest $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $this->dealService->markLost($deal, $request->validated('lost_reason'), $request->user());

        return back()->with('success', "Opportunity '{$deal->title}' marked as CLOSED LOST.");
    }

    /**
     * Reopen a closed deal.
     */
    public function reopen(Request $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $this->dealService->reopenDeal($deal, $request->user());

        return back()->with('success', "Opportunity '{$deal->title}' reopened into active pipeline.");
    }
}
