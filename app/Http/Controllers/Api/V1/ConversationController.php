<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\AssignConversationRequest;
use App\Http\Requests\Conversation\CreateConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationModeRequest;
use App\Http\Requests\Conversation\UpdateConversationStatusRequest;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversation\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        protected ConversationService $conversationService
    ) {}

    /**
     * Display a listing of conversations with multi-attribute filtering.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $filters = $request->only(['status', 'mode', 'contact_id', 'assigned_user_id', 'search', 'per_page']);
        $paginator = $this->conversationService->getPaginatedConversations($filters);

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
        ]);
    }

    /**
     * Store a newly created conversation for a contact.
     */
    public function store(CreateConversationRequest $request): JsonResponse
    {
        Gate::authorize('create', Conversation::class);

        $contact = Contact::findOrFail($request->validated('contact_id'));
        $mode = $request->validated('mode')
            ? ConversationMode::from($request->validated('mode'))
            : ConversationMode::Hybrid;

        $assignedUser = $request->validated('assigned_user_id')
            ? User::find($request->validated('assigned_user_id'))
            : null;

        $conversation = $this->conversationService->createConversation(
            contact: $contact,
            mode: $mode,
            assignedUser: $assignedUser,
            subject: $request->validated('subject')
        );

        if ($request->filled('initial_message')) {
            $this->conversationService->sendOutboundMessage(
                conversation: $conversation,
                body: (string) $request->validated('initial_message'),
                sender: $request->user()
            );
        }

        $conversation->load(['contact', 'assignedUser.profile', 'latestMessage']);

        return response()->json([
            'message' => 'Conversation initiated successfully.',
            'data' => $conversation,
        ], 201);
    }

    /**
     * Display the specified conversation details.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation->load([
            'contact',
            'assignedUser.profile',
            'account',
            'messages' => fn ($query) => $query->latest()->limit(50),
        ]);

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * Update conversation mode (ai, human, hybrid).
     */
    public function updateMode(UpdateConversationModeRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $mode = ConversationMode::from($request->validated('mode'));
        $conversation = $this->conversationService->updateMode($conversation, $mode);

        return response()->json([
            'message' => "Conversation mode changed to {$mode->label()}.",
            'data' => $conversation,
        ]);
    }

    /**
     * Update conversation status (open, pending, closed).
     */
    public function updateStatus(UpdateConversationStatusRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('update', $conversation);

        $status = ConversationStatus::from($request->validated('status'));
        $conversation = $this->conversationService->updateStatus($conversation, $status);

        return response()->json([
            'message' => "Conversation status updated to {$status->label()}.",
            'data' => $conversation,
        ]);
    }

    /**
     * Assign or reassign conversation to an agent.
     */
    public function assign(AssignConversationRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('assign', $conversation);

        $user = $request->validated('assigned_user_id')
            ? User::find($request->validated('assigned_user_id'))
            : null;

        $conversation = $this->conversationService->assignUser($conversation, $user);
        $conversation->load('assignedUser.profile');

        return response()->json([
            'message' => $user ? "Conversation assigned to {$user->name}." : 'Conversation unassigned.',
            'data' => $conversation,
        ]);
    }

    /**
     * Mark all incoming messages in the conversation as read.
     */
    public function markRead(Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $conversation = $this->conversationService->markConversationRead($conversation);

        return response()->json([
            'message' => 'Conversation marked as read.',
            'data' => $conversation,
        ]);
    }

    /**
     * Retrieve all conversations for a specific contact.
     */
    public function byContact(Contact $contact): JsonResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $conversations = $contact->conversations()
            ->with(['assignedUser.profile', 'latestMessage'])
            ->get();

        return response()->json([
            'data' => $conversations,
        ]);
    }
}
