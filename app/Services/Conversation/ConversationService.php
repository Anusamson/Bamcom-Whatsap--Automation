<?php

namespace App\Services\Conversation;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\MessageDeliveryStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppMessageService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(
        protected WhatsAppMessageService $messageService
    ) {}

    /**
     * Get paginated conversations with multi-attribute filtering.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<Conversation>
     */
    public function getPaginatedConversations(array $filters = []): LengthAwarePaginator
    {
        $query = Conversation::query()
            ->with([
                'contact',
                'assignedUser.profile',
                'latestMessage',
                'account',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (isset($filters['assigned_user_id'])) {
            if ($filters['assigned_user_id'] === 'unassigned' || $filters['assigned_user_id'] === null) {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', $filters['assigned_user_id']);
            }
        }

        // Quick filter tabs: all, mine, unassigned, unread, ai, human, hybrid, hot_leads
        $tab = strtolower((string) ($filters['tab'] ?? 'all'));
        match ($tab) {
            'mine' => $query->where('assigned_user_id', $filters['current_user_id'] ?? auth()->id()),
            'unassigned' => $query->whereNull('assigned_user_id'),
            'unread' => $query->where('unread_count', '>', 0),
            'ai' => $query->where('mode', ConversationMode::Ai),
            'human' => $query->where('mode', ConversationMode::Human),
            'hybrid' => $query->where('mode', ConversationMode::Hybrid),
            'hot_leads' => $query->whereHas('contact.leads', function ($q): void {
                $q->where(fn ($sub) => $sub->where('score', '>=', 70)->orWhere('temperature', 'hot'));
            }),
            default => null,
        };

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Locate existing active conversation or create a new open conversation.
     */
    public function findOrCreateActiveConversation(Contact $contact, ?int $accountId = null): Conversation
    {
        $active = Conversation::query()
            ->where('contact_id', $contact->id)
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->latest('last_message_at')
            ->first();

        if ($active) {
            if ($active->status === ConversationStatus::Pending) {
                $active->update(['status' => ConversationStatus::Open]);
            }

            if ($accountId && ! $active->whatsapp_account_id) {
                $active->update(['whatsapp_account_id' => $accountId]);
            }

            return $active;
        }

        return Conversation::create([
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $accountId,
            'assigned_user_id' => $contact->assigned_user_id,
            'mode' => ConversationMode::Hybrid,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);
    }

    /**
     * Explicitly start a new conversation for a contact.
     */
    public function createConversation(
        Contact $contact,
        ConversationMode $mode = ConversationMode::Hybrid,
        ?User $assignedUser = null,
        ?string $subject = null,
        ?int $accountId = null
    ): Conversation {
        return Conversation::create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $assignedUser?->id ?? $contact->assigned_user_id,
            'whatsapp_account_id' => $accountId,
            'mode' => $mode,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'subject' => $subject,
            'last_message_at' => now(),
            'unread_count' => 0,
        ]);
    }

    /**
     * Change conversation mode (ai, human, hybrid).
     */
    public function updateMode(Conversation $conversation, ConversationMode $mode): Conversation
    {
        $conversation->update(['mode' => $mode]);

        return $conversation;
    }

    /**
     * Change conversation status (open, pending, closed).
     */
    public function updateStatus(Conversation $conversation, ConversationStatus $status): Conversation
    {
        $updateData = ['status' => $status];

        if ($status === ConversationStatus::Closed) {
            $updateData['closed_at'] = now();
        } elseif ($conversation->isClosed()) {
            $updateData['closed_at'] = null;
        }

        $conversation->update($updateData);

        return $conversation;
    }

    /**
     * Assign conversation to a sales rep / agent.
     */
    public function assignUser(Conversation $conversation, ?User $user): Conversation
    {
        $conversation->update([
            'assigned_user_id' => $user?->id,
        ]);

        return $conversation;
    }

    /**
     * Transmit and persist an outbound message to a contact in a conversation.
     *
     * @param  array<int, string|int|float>  $templateParams
     *
     * @throws Exception
     */
    public function sendOutboundMessage(
        Conversation $conversation,
        string $body,
        ?User $sender = null,
        string $type = 'text',
        ?string $templateName = null,
        array $templateParams = []
    ): Message {
        $contact = $conversation->contact;

        if ($type === 'template' && ! empty($templateName)) {
            $result = $this->messageService->sendTemplateMessage(
                to: $contact->phone,
                templateName: $templateName,
                bodyParameters: $templateParams,
                contact: $contact,
                sender: $sender
            );
        } else {
            $result = $this->messageService->sendTextMessage(
                to: $contact->phone,
                body: $body,
                contact: $contact,
                sender: $sender
            );
        }

        $deliveryStatus = ($result['success'] ?? false)
            ? MessageDeliveryStatus::Sent
            : MessageDeliveryStatus::Failed;

        $senderPhone = $conversation->account?->display_phone_number
            ?: (string) config('whatsapp.phone_number_id', 'Bamcom');

        $messageId = $result['message_id'] ?? null;
        $message = $messageId ? Message::where('meta_message_id', $messageId)->first() : null;

        if ($message) {
            $message->update([
                'conversation_id' => $conversation->id,
                'body' => $body ?: $message->body,
            ]);
        } else {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'sender_type' => $sender ? 'user' : 'system',
                'sender_id' => $sender?->id,
                'meta_message_id' => $messageId,
                'direction' => 'outbound',
                'sender_phone' => $senderPhone,
                'recipient_phone' => $contact->phone,
                'type' => $type,
                'body' => $body,
                'delivery_status' => $deliveryStatus,
                'is_read' => false,
                'sent_at' => now(),
                'error_message' => $result['error'] ?? null,
                'payload' => $result['response'] ?? null,
            ]);
        }

        $conversation->update([
            'last_message_at' => now(),
        ]);

        return $message;
    }

    /**
     * Mark all unread inbound messages in a conversation as read.
     */
    public function markConversationRead(Conversation $conversation): Conversation
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->messages()
                ->where('direction', 'inbound')
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                    'delivery_status' => MessageDeliveryStatus::Read->value,
                ]);

            $conversation->resetUnread();
        });

        return $conversation;
    }

    /**
     * Compute real-time counts for the 8 inbox filter tabs.
     *
     * @return array<string, int>
     */
    public function getInboxFilterCounts(?User $user = null): array
    {
        $userId = $user?->id ?? auth()->id();

        return [
            'all' => Conversation::count(),
            'mine' => $userId ? Conversation::where('assigned_user_id', $userId)->count() : 0,
            'unassigned' => Conversation::whereNull('assigned_user_id')->count(),
            'unread' => Conversation::where('unread_count', '>', 0)->count(),
            'ai' => Conversation::where('mode', ConversationMode::Ai)->count(),
            'human' => Conversation::where('mode', ConversationMode::Human)->count(),
            'hybrid' => Conversation::where('mode', ConversationMode::Hybrid)->count(),
            'hot_leads' => Conversation::whereHas('contact.leads', function ($q): void {
                $q->where(fn ($sub) => $sub->where('score', '>=', 70)->orWhere('temperature', 'hot'));
            })->count(),
        ];
    }

    /**
     * Eagerly load all relations required for the team inbox active conversation pane.
     */
    public function loadActiveConversationDetails(Conversation $conversation): Conversation
    {
        return $conversation->load([
            'contact.leads.property.estate',
            'contact.leads.stage',
            'contact.leads.pipeline',
            'contact.leads.activities',
            'contact.deals.property.estate',
            'contact.deals.stage',
            'assignedUser.profile',
            'account',
            'messages' => fn ($q) => $q->oldest('created_at'),
        ]);
    }
}
