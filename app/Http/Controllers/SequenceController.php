<?php

namespace App\Http\Controllers;

use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Models\Contact;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\SequenceEnrollment;
use App\Models\User;
use App\Services\Sequence\SequenceService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SequenceController extends Controller
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    /**
     * Display a listing of follow-up sequences.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $query = FollowUpSequence::query()
            ->with(['creator:id,name', 'steps'])
            ->withCount([
                'steps',
                'enrollments as total_enrollments_count',
                'activeEnrollments as active_enrollments_count',
                'completedEnrollments as completed_enrollments_count',
            ])
            ->latest();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sequences = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_sequences' => FollowUpSequence::count(),
            'active_sequences' => FollowUpSequence::where('status', SequenceStatus::Active->value)->count(),
            'total_enrollments' => SequenceEnrollment::count(),
            'active_enrollments' => SequenceEnrollment::where('status', SequenceEnrollmentStatus::Active->value)->count(),
            'completed_enrollments' => SequenceEnrollment::where('status', SequenceEnrollmentStatus::Completed->value)->count(),
            'cancelled_enrollments' => SequenceEnrollment::where('status', SequenceEnrollmentStatus::Cancelled->value)->count(),
        ];

        return Inertia::render('Sequences/Index', [
            'sequences' => $sequences,
            'metrics' => $metrics,
            'filters' => [
                'status' => $status ?? 'all',
                'search' => $search ?? '',
            ],
            'statuses' => array_map(fn (SequenceStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'badge' => $s->badgeClass(),
            ], SequenceStatus::cases()),
        ]);
    }

    /**
     * Show form for creating a new sequence.
     */
    public function create(): Response
    {
        return Inertia::render('Sequences/Create', [
            'pipelineStages' => PipelineStage::query()->orderBy('order_column')->get(['id', 'name', 'pipeline_id']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }

    /**
     * Store a newly created sequence.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:draft,active,paused,archived',
            'trigger_type' => 'required|string',
            'trigger_config' => 'nullable|array',
            'exit_on_deal_won' => 'boolean',
            'exit_on_reply' => 'boolean',
            'steps' => 'nullable|array',
            'steps.*.name' => 'nullable|string|max:255',
            'steps.*.step_number' => 'nullable|integer',
            'steps.*.delay_minutes' => 'nullable|integer|min:0',
            'steps.*.delay_type' => 'nullable|string|in:minutes,hours,days',
            'steps.*.whatsapp_config' => 'nullable|array',
            'steps.*.task_config' => 'nullable|array',
            'steps.*.stage_change_config' => 'nullable|array',
            'steps.*.tag_config' => 'nullable|array',
            'steps.*.assignment_config' => 'nullable|array',
            'steps.*.notification_config' => 'nullable|array',
            'steps.*.applicability_rules' => 'nullable|array',
        ]);

        $sequence = $this->sequenceService->createSequence($validated, $request->user());

        return redirect()->route('sequences.show', $sequence)
            ->with('success', "Follow-up sequence '{$sequence->name}' created successfully.");
    }

    /**
     * Display sequence details, steps, and enrollments.
     */
    public function show(FollowUpSequence $sequence): Response
    {
        $sequence->load([
            'creator:id,name',
            'steps' => fn ($q) => $q->orderBy('step_number'),
        ]);

        $enrollments = $sequence->enrollments()
            ->with(['contact:id,first_name,last_name,phone,has_opted_out', 'lead:id,title,status', 'enrolledBy:id,name'])
            ->latest('enrolled_at')
            ->paginate(15);

        $metrics = [
            'total_enrollments' => $sequence->enrollments()->count(),
            'active_enrollments' => $sequence->enrollments()->where('status', SequenceEnrollmentStatus::Active->value)->count(),
            'completed_enrollments' => $sequence->enrollments()->where('status', SequenceEnrollmentStatus::Completed->value)->count(),
            'cancelled_enrollments' => $sequence->enrollments()->where('status', SequenceEnrollmentStatus::Cancelled->value)->count(),
        ];

        // Available contacts for manual enrollment modal
        $availableContacts = Contact::query()
            ->select('id', 'first_name', 'last_name', 'phone', 'has_opted_out')
            ->where('has_opted_out', false)
            ->whereDoesntHave('sequenceEnrollments', function ($q) use ($sequence): void {
                $q->where('sequence_id', $sequence->id)
                    ->where('status', SequenceEnrollmentStatus::Active->value);
            })
            ->orderBy('first_name')
            ->limit(100)
            ->get();

        return Inertia::render('Sequences/Show', [
            'sequence' => $sequence,
            'enrollments' => $enrollments,
            'metrics' => $metrics,
            'availableContacts' => $availableContacts,
            'pipelineStages' => PipelineStage::query()->orderBy('order_column')->get(['id', 'name']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }

    /**
     * Show form for editing an existing sequence.
     */
    public function edit(FollowUpSequence $sequence): Response
    {
        $sequence->load(['steps' => fn ($q) => $q->orderBy('step_number')]);

        return Inertia::render('Sequences/Edit', [
            'sequence' => $sequence,
            'pipelineStages' => PipelineStage::query()->orderBy('order_column')->get(['id', 'name', 'pipeline_id']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email', 'role']),
        ]);
    }

    /**
     * Update an existing sequence.
     */
    public function update(Request $request, FollowUpSequence $sequence): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:draft,active,paused,archived',
            'trigger_type' => 'required|string',
            'trigger_config' => 'nullable|array',
            'exit_on_deal_won' => 'boolean',
            'exit_on_reply' => 'boolean',
            'steps' => 'nullable|array',
            'steps.*.id' => 'nullable|integer',
            'steps.*.name' => 'nullable|string|max:255',
            'steps.*.step_number' => 'nullable|integer',
            'steps.*.delay_minutes' => 'nullable|integer|min:0',
            'steps.*.delay_type' => 'nullable|string|in:minutes,hours,days',
            'steps.*.whatsapp_config' => 'nullable|array',
            'steps.*.task_config' => 'nullable|array',
            'steps.*.stage_change_config' => 'nullable|array',
            'steps.*.tag_config' => 'nullable|array',
            'steps.*.assignment_config' => 'nullable|array',
            'steps.*.notification_config' => 'nullable|array',
            'steps.*.applicability_rules' => 'nullable|array',
        ]);

        $this->sequenceService->updateSequence($sequence, $validated);

        return redirect()->route('sequences.show', $sequence)
            ->with('success', "Follow-up sequence '{$sequence->name}' updated successfully.");
    }

    /**
     * Delete sequence.
     */
    public function destroy(FollowUpSequence $sequence): RedirectResponse
    {
        $name = $sequence->name;
        $this->sequenceService->deleteSequence($sequence);

        return redirect()->route('sequences.index')
            ->with('success', "Sequence '{$name}' deleted successfully.");
    }

    /**
     * Toggle active/paused status.
     */
    public function toggleStatus(FollowUpSequence $sequence): RedirectResponse
    {
        $this->sequenceService->toggleStatus($sequence);

        return back()->with('success', "Sequence status changed to {$sequence->fresh()->status->label()}.");
    }

    /**
     * Enroll a contact into sequence.
     */
    public function enrollContact(Request $request, FollowUpSequence $sequence): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'lead_id' => 'nullable|exists:leads,id',
        ]);

        $contact = Contact::findOrFail($validated['contact_id']);
        $lead = ! empty($validated['lead_id']) ? Lead::find($validated['lead_id']) : null;

        try {
            $this->sequenceService->enroll($contact, $sequence, $lead, $request->user());

            return back()->with('success', "Contact {$contact->full_name} enrolled into '{$sequence->name}' successfully.");
        } catch (Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Unenroll an enrollment.
     */
    public function unenrollContact(Request $request, SequenceEnrollment $enrollment): RedirectResponse
    {
        $reason = $request->input('reason', 'Manual unenrollment by user');
        $this->sequenceService->unenroll($enrollment, $reason, $request->user());

        return back()->with('success', 'Contact unenrolled from sequence successfully.');
    }

    /**
     * Opt out contact from all communications and sequences.
     */
    public function optOutContact(Request $request, Contact $contact): RedirectResponse
    {
        $reason = $request->input('reason', 'Customer requested opt-out');
        $contact->optOut($reason);

        return back()->with('success', "Contact {$contact->full_name} has been marked as opted out and active sequences cancelled.");
    }

    /**
     * Opt in contact back.
     */
    public function optInContact(Contact $contact): RedirectResponse
    {
        $contact->optIn();

        return back()->with('success', "Contact {$contact->full_name} opt-out status cleared.");
    }
}
