<?php

namespace App\Http\Controllers;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\HandoverTrigger;
use App\Http\Requests\Conversation\AssignConversationRequest;
use App\Http\Requests\Conversation\SendMessageRequest;
use App\Http\Requests\Conversation\UpdateConversationModeRequest;
use App\Http\Requests\Conversation\UpdateConversationStatusRequest;
use App\Models\Activity;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\AI\HandoverService;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationInboxController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * Display the 3-column WhatsApp team inbox.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Conversation::class);

        $tab = strtolower((string) $request->input('tab', 'all'));
        $search = (string) $request->input('search', '');
        $selectedId = $request->input('conversation_id');

        $filters = [
            'tab' => $tab,
            'search' => $search,
            'current_user_id' => $request->user()->id,
            'per_page' => 50,
        ];

        $conversations = $this->conversationService->getPaginatedConversations($filters);
        $counts = $this->conversationService->getInboxFilterCounts($request->user());

        // Determine active conversation
        $activeConversation = null;
        if ($selectedId) {
            $activeConversation = Conversation::find($selectedId);
        }

        if (! $activeConversation && $conversations->count() > 0) {
            $activeConversation = $conversations->first();
        }

        if ($activeConversation) {
            $activeConversation = $this->conversationService->loadActiveConversationDetails($activeConversation);
        }

        // Supporting data for controls, templates, and assignments
        $users = User::query()
            ->select('id', 'name', 'email', 'role')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $templates = WhatsAppTemplate::query()
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get();

        $estates = Estate::query()
            ->select('id', 'name', 'location')
            ->orderBy('name')
            ->get();

        $modes = array_map(fn (ConversationMode $mode): array => [
            'value' => $mode->value,
            'label' => $mode->label(),
            'badge' => $mode->badgeClass(),
        ], ConversationMode::cases());

        $statuses = array_map(fn (ConversationStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
            'badge' => $status->badgeClass(),
        ], ConversationStatus::cases());

        return Inertia::render('Conversations/Inbox', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'conversation_id' => $activeConversation?->id,
            ],
            'counts' => $counts,
            'users' => $users,
            'templates' => $templates,
            'estates' => $estates,
            'modes' => $modes,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Send an outbound message from the inbox reply composer.
     */
    public function sendMessage(SendMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $body = (string) ($request->validated('body') ?? '');
        $type = (string) ($request->validated('type') ?? 'text');
        $templateName = $request->validated('template_name');
        $templateParams = (array) ($request->validated('template_parameters') ?? []);

        $this->conversationService->sendOutboundMessage(
            conversation: $conversation,
            body: $body,
            sender: $request->user(),
            type: $type,
            templateName: $templateName,
            templateParams: $templateParams
        );

        return back()->with('success', 'Message dispatched via WhatsApp.');
    }

    /**
     * Update conversation mode (ai, human, hybrid).
     */
    public function updateMode(UpdateConversationModeRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        $mode = ConversationMode::from($request->validated('mode'));
        $this->conversationService->updateMode($conversation, $mode);

        return back()->with('success', "Conversation mode switched to {$mode->label()}.");
    }

    /**
     * Update conversation status (open, pending, closed).
     */
    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        $status = ConversationStatus::from($request->validated('status'));
        $this->conversationService->updateStatus($conversation, $status);

        return back()->with('success', "Conversation status changed to {$status->label()}.");
    }

    /**
     * Assign or reassign conversation to a sales representative.
     */
    public function assign(AssignConversationRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('assign', $conversation);

        $user = $request->validated('assigned_user_id')
            ? User::find($request->validated('assigned_user_id'))
            : null;

        $this->conversationService->assignUser($conversation, $user);

        return back()->with('success', $user ? "Assigned to {$user->name}." : 'Conversation unassigned.');
    }

    /**
     * Mark all incoming messages in conversation as read.
     */
    public function markRead(Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);

        $this->conversationService->markConversationRead($conversation);

        return back()->with('success', 'Conversation marked as read.');
    }

    /**
     * Schedule a property inspection directly from the right panel.
     */
    public function scheduleInspection(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        $validated = $request->validate([
            'estate_name' => ['required', 'string', 'max:255'],
            'inspection_date' => ['required', 'date'],
            'inspection_time' => ['required', 'string', 'max:50'],
            'inspector_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact = $conversation->contact;
        $lead = $contact->leads()->latest()->first();

        if (! $lead) {
            $lead = Lead::create([
                'contact_id' => $contact->id,
                'assigned_user_id' => $validated['inspector_id'] ?? $conversation->assigned_user_id ?? $request->user()->id,
                'title' => "Inspection Inquiry: {$validated['estate_name']}",
                'lead_source' => $contact->lead_source,
                'property_interest' => $validated['estate_name'],
                'notes' => $validated['notes'] ?? 'Scheduled via WhatsApp Inbox',
            ]);
        }

        Activity::create([
            'user_id' => $request->user()->id,
            'lead_id' => $lead->id,
            'activity_type' => 'inspection_scheduled',
            'description' => "Inspection scheduled at {$validated['estate_name']} for {$validated['inspection_date']} at {$validated['inspection_time']}.",
            'properties' => [
                'estate_name' => $validated['estate_name'],
                'inspection_date' => $validated['inspection_date'],
                'inspection_time' => $validated['inspection_time'],
                'inspector_id' => $validated['inspector_id'] ?? null,
                'scheduled_by' => $request->user()->name,
                'notes' => $validated['notes'] ?? null,
                'status' => 'scheduled',
            ],
        ]);

        return back()->with('success', "Inspection scheduled at {$validated['estate_name']}.");
    }

    /**
     * Return conversation to AI or Hybrid mode (authorized users only).
     */
    public function resumeAi(Request $request, Conversation $conversation, HandoverService $handoverService): RedirectResponse
    {
        Gate::authorize('resumeAi', $conversation);

        $validated = $request->validate([
            'mode' => ['required_without:target_mode', 'nullable', 'string', 'in:ai,hybrid'],
            'target_mode' => ['required_without:mode', 'nullable', 'string', 'in:ai,hybrid'],
        ]);

        $modeStr = (string) ($validated['mode'] ?? $validated['target_mode']);
        $mode = ConversationMode::from($modeStr);
        $handoverService->resumeAi($conversation, $mode, $request->user());

        return back()->with('success', "Conversation returned to {$mode->label()}.");
    }

    /**
     * Manually trigger AI-to-human handover.
     */
    public function triggerHandover(Request $request, Conversation $conversation, HandoverService $handoverService): RedirectResponse
    {
        Gate::authorize('update', $conversation);

        $validated = $request->validate([
            'trigger' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $trigger = ! empty($validated['trigger'])
            ? HandoverTrigger::tryFrom($validated['trigger']) ?? HandoverTrigger::CustomerRequest
            : HandoverTrigger::CustomerRequest;

        $handoverService->executeHandover($conversation, $trigger, [
            'reason' => $validated['reason'] ?? 'Manual representative handover requested from inbox.',
        ]);

        return back()->with('success', 'Conversation handed over to sales representative.');
    }
}
