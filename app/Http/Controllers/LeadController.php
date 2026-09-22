<?php

namespace App\Http\Controllers;

use App\DTOs\Lead\CreateLeadDTO;
use App\DTOs\Lead\UpdateLeadDTO;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Http\Requests\Lead\CreateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Services\Lead\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService
    ) {}

    /**
     * Display a listing of sales opportunities with multifaceted filters.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $filters = $request->only([
            'status',
            'temperature',
            'agent',
            'assigned_user_id',
            'source',
            'lead_source',
            'property',
            'property_interest',
            'date',
            'date_from',
            'from_date',
            'date_to',
            'to_date',
            'contact_id',
            'search',
            'per_page',
        ]);

        $leads = $this->leadService->getPaginatedLeads($filters);
        $metrics = $this->leadService->getLeadMetrics();

        $agents = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (LeadStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], LeadStatus::cases());

        $temperatures = array_map(fn (LeadTemperature $temp): array => [
            'value' => $temp->value,
            'label' => $temp->label(),
            'icon' => $temp->icon(),
            'badge' => $temp->badgeClass(),
        ], LeadTemperature::cases());

        $sources = array_map(fn (LeadSource $source): array => [
            'value' => $source->value,
            'label' => $source->label(),
            'badge' => $source->badgeClass(),
        ], LeadSource::cases());

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'filters' => $filters,
            'metrics' => $metrics,
            'agents' => $agents,
            'statuses' => $statuses,
            'temperatures' => $temperatures,
            'sources' => $sources,
        ]);
    }

    /**
     * Show the form for creating a new sales opportunity.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Lead::class);

        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone', 'email', 'location')
            ->orderBy('first_name')
            ->get();

        $agents = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (LeadStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
        ], LeadStatus::cases());

        $temperatures = array_map(fn (LeadTemperature $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
            'icon' => $t->icon(),
        ], LeadTemperature::cases());

        $timelines = array_map(fn (PurchaseTimeline $pt): array => [
            'value' => $pt->value,
            'label' => $pt->label(),
        ], PurchaseTimeline::cases());

        $qualifications = array_map(fn (QualificationStatus $qs): array => [
            'value' => $qs->value,
            'label' => $qs->label(),
        ], QualificationStatus::cases());

        $sources = array_map(fn (LeadSource $ls): array => [
            'value' => $ls->value,
            'label' => $ls->label(),
        ], LeadSource::cases());

        return Inertia::render('Leads/Create', [
            'contacts' => $contacts,
            'agents' => $agents,
            'statuses' => $statuses,
            'temperatures' => $temperatures,
            'timelines' => $timelines,
            'qualifications' => $qualifications,
            'sources' => $sources,
            'preselectedContactId' => $request->integer('contact_id') ?: null,
        ]);
    }

    /**
     * Store a newly created sales opportunity.
     */
    public function store(CreateLeadRequest $request): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $dto = CreateLeadDTO::fromArray($request->validated());
        $lead = $this->leadService->createLead($dto);

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', 'Opportunity "'.$lead->title.'" registered successfully.');
    }

    /**
     * Display the specified lead opportunity 360 view.
     */
    public function show(Lead $lead): Response
    {
        Gate::authorize('view', $lead);

        $lead->load(['contact', 'assignedUser.profile', 'assignedUser.team']);

        $agents = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (LeadStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
            'badge' => $s->badgeClass(),
        ], LeadStatus::cases());

        $temperatures = array_map(fn (LeadTemperature $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
            'icon' => $t->icon(),
            'badge' => $t->badgeClass(),
        ], LeadTemperature::cases());

        return Inertia::render('Leads/Show', [
            'lead' => $lead,
            'agents' => $agents,
            'statuses' => $statuses,
            'temperatures' => $temperatures,
        ]);
    }

    /**
     * Show the form for editing the sales opportunity.
     */
    public function edit(Lead $lead): Response
    {
        Gate::authorize('update', $lead);

        $lead->load(['contact', 'assignedUser']);

        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone', 'email')
            ->orderBy('first_name')
            ->get();

        $agents = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (LeadStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
        ], LeadStatus::cases());

        $temperatures = array_map(fn (LeadTemperature $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
            'icon' => $t->icon(),
        ], LeadTemperature::cases());

        $timelines = array_map(fn (PurchaseTimeline $pt): array => [
            'value' => $pt->value,
            'label' => $pt->label(),
        ], PurchaseTimeline::cases());

        $qualifications = array_map(fn (QualificationStatus $qs): array => [
            'value' => $qs->value,
            'label' => $qs->label(),
        ], QualificationStatus::cases());

        $sources = array_map(fn (LeadSource $ls): array => [
            'value' => $ls->value,
            'label' => $ls->label(),
        ], LeadSource::cases());

        return Inertia::render('Leads/Edit', [
            'lead' => $lead,
            'contacts' => $contacts,
            'agents' => $agents,
            'statuses' => $statuses,
            'temperatures' => $temperatures,
            'timelines' => $timelines,
            'qualifications' => $qualifications,
            'sources' => $sources,
        ]);
    }

    /**
     * Update the sales opportunity.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $dto = UpdateLeadDTO::fromArray($request->validated());
        $updated = $this->leadService->updateLead($lead, $dto);

        return redirect()
            ->route('leads.show', $updated)
            ->with('success', 'Opportunity "'.$updated->title.'" updated successfully.');
    }

    /**
     * Archive / soft delete the lead opportunity.
     */
    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $title = $lead->title;
        $this->leadService->deleteLead($lead);

        return redirect()
            ->route('leads.index')
            ->with('success', 'Opportunity "'.$title.'" archived successfully.');
    }

    /**
     * Quick status / sales stage update.
     */
    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', LeadStatus::values())],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $status = LeadStatus::from($validated['status']);
        $this->leadService->updateStatus($lead, $status, $validated['lost_reason'] ?? null);

        return back()->with('success', 'Opportunity advanced to '.$status->label().'.');
    }

    /**
     * Quick sales representative assignment.
     */
    public function assign(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('assign', $lead);

        $validated = $request->validate([
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->leadService->assignRepresentative($lead, $validated['assigned_user_id'] ?? null);

        return back()->with('success', 'Lead representative updated.');
    }
}
