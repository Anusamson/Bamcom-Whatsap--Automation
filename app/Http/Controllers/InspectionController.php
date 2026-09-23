<?php

namespace App\Http\Controllers;

use App\Enums\InspectionStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use App\Services\Inspection\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InspectionController extends Controller
{
    public function __construct(
        protected InspectionService $inspectionService
    ) {}

    /**
     * Display a listing of site inspections with List and Calendar views.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Inspection::class);

        $filters = [
            'status' => $request->input('status'),
            'representative_id' => $request->input('representative_id'),
            'property_id' => $request->input('property_id'),
            'contact_id' => $request->input('contact_id'),
            'search' => $request->input('search'),
            'date' => $request->input('date'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'view' => $request->input('view', 'list'), // 'list' or 'calendar'
        ];

        $inspections = $this->inspectionService->getPaginatedInspections($filters, 15);

        // Calendar events for the active view
        $calendarEvents = $this->inspectionService->getCalendarEvents(
            $request->input('cal_start'),
            $request->input('cal_end'),
            $request->input('representative_id') ? (int) $request->input('representative_id') : null,
            $request->input('status')
        );

        // Aggregate statistics for ribbon
        $stats = [
            'total' => Inspection::count(),
            'scheduled' => Inspection::where('status', InspectionStatus::Scheduled->value)->count(),
            'confirmed' => Inspection::where('status', InspectionStatus::Confirmed->value)->count(),
            'completed' => Inspection::where('status', InspectionStatus::Completed->value)->count(),
            'cancelled' => Inspection::whereIn('status', [InspectionStatus::Cancelled->value, InspectionStatus::NoShow->value])->count(),
            'today' => Inspection::whereDate('inspection_date', today())->count(),
        ];

        // Reference lists for scheduling & filters
        $representatives = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $properties = Property::query()
            ->select('id', 'title', 'location')
            ->orderBy('title')
            ->get();

        $contacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone', 'email')
            ->latest('last_contact_at')
            ->limit(50)
            ->get();

        return Inertia::render('Inspections/Index', [
            'inspections' => $inspections,
            'calendarEvents' => $calendarEvents,
            'stats' => $stats,
            'filters' => $filters,
            'statuses' => array_map(fn (InspectionStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'badgeClass' => $s->badgeClass(),
            ], InspectionStatus::cases()),
            'representatives' => $representatives,
            'properties' => $properties,
            'contacts' => $contacts,
        ]);
    }

    /**
     * API / Ajax endpoint for dynamic calendar event queries.
     */
    public function calendar(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Inspection::class);

        $events = $this->inspectionService->getCalendarEvents(
            $request->input('start'),
            $request->input('end'),
            $request->input('representative_id') ? (int) $request->input('representative_id') : null,
            $request->input('status')
        );

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Schedule a new physical site inspection.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Inspection::class);

        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'property_id' => ['nullable', 'exists:properties,id'],
            'estate_name' => ['nullable', 'string', 'max:255'],
            'representative_id' => ['nullable', 'exists:users,id'],
            'inspection_date' => ['required', 'date'],
            'inspection_time' => ['required', 'string', 'max:50'],
            'meeting_point' => ['nullable', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'sales_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $inspection = $this->inspectionService->scheduleInspection($validated, $request->user());

        return redirect()->route('inspections.show', $inspection->id)
            ->with('success', "Site inspection scheduled for {$inspection->formatted_date_time}.");
    }

    /**
     * Display detailed inspection profile, lead attributes, and outcome log.
     */
    public function show(Inspection $inspection): Response
    {
        Gate::authorize('view', $inspection);

        $inspection->load([
            'contact.leads',
            'lead.assignedUser',
            'property.estate',
            'property.activePrice',
            'representative.profile',
            'creator',
        ]);

        // Audit activities for this inspection and contact
        $activities = Activity::query()
            ->where(function ($q) use ($inspection): void {
                $q->where('lead_id', $inspection->lead_id)
                    ->orWhere('properties->inspection_id', $inspection->id);
            })
            ->with('user')
            ->latest('id')
            ->limit(25)
            ->get();

        $representatives = User::query()
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inspections/Show', [
            'inspection' => $inspection,
            'activities' => $activities,
            'representatives' => $representatives,
            'statuses' => array_map(fn (InspectionStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'badgeClass' => $s->badgeClass(),
            ], InspectionStatus::cases()),
        ]);
    }

    /**
     * Update details of an existing inspection.
     */
    public function update(Request $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);

        $validated = $request->validate([
            'property_id' => ['nullable', 'exists:properties,id'],
            'estate_name' => ['nullable', 'string', 'max:255'],
            'meeting_point' => ['required', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'sales_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $inspection->update($validated);

        return back()->with('success', 'Inspection details updated successfully.');
    }

    /**
     * Update the status of an inspection (confirm, complete, cancel, etc.).
     */
    public function updateStatus(Request $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', InspectionStatus::values())],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'sales_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->inspectionService->updateStatus(
            $inspection,
            InspectionStatus::from($validated['status']),
            $validated,
            $request->user()
        );

        return back()->with('success', "Inspection status updated to {$inspection->status->label()}.");
    }

    /**
     * Reschedule an inspection to a new date and time.
     */
    public function reschedule(Request $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);

        $validated = $request->validate([
            'inspection_date' => ['required', 'date'],
            'inspection_time' => ['required', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'representative_id' => ['nullable', 'exists:users,id'],
        ]);

        $this->inspectionService->reschedule(
            $inspection,
            $validated['inspection_date'],
            $validated['inspection_time'],
            $validated['reason'] ?? null,
            ! empty($validated['representative_id']) ? (int) $validated['representative_id'] : null,
            $request->user()
        );

        return back()->with('success', "Inspection rescheduled to {$inspection->formatted_date_time}.");
    }

    /**
     * Reassign field sales representative with conflict check.
     */
    public function assignRepresentative(Request $request, Inspection $inspection): RedirectResponse
    {
        Gate::authorize('update', $inspection);

        $validated = $request->validate([
            'representative_id' => ['required', 'exists:users,id'],
        ]);

        $representative = User::findOrFail($validated['representative_id']);

        $this->inspectionService->assignRepresentative($inspection, $representative, $request->user());

        return back()->with('success', "Representative {$representative->name} assigned to inspection.");
    }

    /**
     * Delete an inspection record.
     */
    public function destroy(Inspection $inspection): RedirectResponse
    {
        Gate::authorize('delete', $inspection);

        $inspection->delete();

        return redirect()->route('inspections.index')->with('success', 'Inspection deleted.');
    }
}
