<?php

namespace App\Http\Controllers;

use App\DTOs\Contact\CreateContactDTO;
use App\DTOs\Contact\UpdateContactDTO;
use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Http\Requests\Contact\CreateContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Models\Contact;
use App\Models\User;
use App\Services\Contact\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    /**
     * Display a paginated listing of contacts with search and filters.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $filters = $request->only(['search', 'status', 'lead_source', 'assigned_user_id', 'per_page']);
        $contacts = $this->contactService->getPaginatedContacts($filters);

        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (ContactStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], ContactStatus::cases());

        $leadSources = array_map(fn (LeadSource $source): array => [
            'value' => $source->value,
            'label' => $source->label(),
            'badge' => $source->badgeClass(),
        ], LeadSource::cases());

        $metrics = $this->contactService->getContactMetrics();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'users' => $users,
            'statuses' => $statuses,
            'leadSources' => $leadSources,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Show the form for creating a new contact.
     */
    public function create(): Response
    {
        Gate::authorize('create', Contact::class);

        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (ContactStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], ContactStatus::cases());

        $leadSources = array_map(fn (LeadSource $source): array => [
            'value' => $source->value,
            'label' => $source->label(),
        ], LeadSource::cases());

        return Inertia::render('Contacts/Create', [
            'users' => $users,
            'statuses' => $statuses,
            'leadSources' => $leadSources,
        ]);
    }

    /**
     * Store a newly created contact with normalized phone.
     */
    public function store(CreateContactRequest $request): RedirectResponse
    {
        Gate::authorize('create', Contact::class);

        $dto = CreateContactDTO::fromArray($request->validated());
        $contact = $this->contactService->createContact($dto);

        return redirect()
            ->route('contacts.show', $contact)
            ->with('success', 'Contact '.$contact->full_name.' registered successfully.');
    }

    /**
     * Display the Contact 360 profile shell.
     */
    public function show(Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        $contact->load([
            'assignedUser.profile',
            'assignedUser.team',
            'leads.assignedUser',
            'deals.property.estate',
            'deals.assignedUser',
            'deals.stage',
        ]);

        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (ContactStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], ContactStatus::cases());

        $leadSources = array_map(fn (LeadSource $source): array => [
            'value' => $source->value,
            'label' => $source->label(),
            'badge' => $source->badgeClass(),
        ], LeadSource::cases());

        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'users' => $users,
            'statuses' => $statuses,
            'leadSources' => $leadSources,
        ]);
    }

    /**
     * Show the form for editing the contact.
     */
    public function edit(Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        $contact->load('assignedUser');

        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $statuses = array_map(fn (ContactStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], ContactStatus::cases());

        $leadSources = array_map(fn (LeadSource $source): array => [
            'value' => $source->value,
            'label' => $source->label(),
        ], LeadSource::cases());

        return Inertia::render('Contacts/Edit', [
            'contact' => $contact,
            'users' => $users,
            'statuses' => $statuses,
            'leadSources' => $leadSources,
        ]);
    }

    /**
     * Update the contact.
     */
    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $dto = UpdateContactDTO::fromArray($request->validated());
        $updated = $this->contactService->updateContact($contact, $dto);

        return redirect()
            ->route('contacts.show', $updated)
            ->with('success', 'Contact '.$updated->full_name.' updated successfully.');
    }

    /**
     * Soft delete/archive the contact.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $name = $contact->full_name;
        $this->contactService->deleteContact($contact);

        return redirect()
            ->route('contacts.index')
            ->with('success', 'Contact '.$name.' has been archived.');
    }

    /**
     * Quick status update for contact.
     */
    public function updateStatus(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ContactStatus::values())],
        ]);

        $status = ContactStatus::from($validated['status']);
        $this->contactService->updateStatus($contact, $status);

        return back()->with('success', 'Contact status changed to '.$status->label().'.');
    }

    /**
     * Record interaction touchpoint timestamp.
     */
    public function logTouchpoint(Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $this->contactService->recordTouchpoint($contact);

        return back()->with('success', 'Interaction touchpoint recorded for '.$contact->full_name.'.');
    }
}
