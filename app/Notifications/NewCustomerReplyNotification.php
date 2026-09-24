<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewCustomerReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Message $message,
        public Conversation $conversation
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $contact = $this->conversation->contact;
        $snippet = Str::limit($this->message->body ?: 'Sent an attachment', 80);

        return [
            'type' => 'new_customer_reply',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'snippet' => $snippet,
            'title' => 'Reply from '.($contact?->full_name ?? 'Client'),
            'message' => ($contact?->full_name ?? 'Client').': "'.$snippet.'"',
            'url' => route('conversations.inbox', ['conversation_id' => $this->conversation->id]),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
