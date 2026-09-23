<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Conversation\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued job for dispatching outbound AI-generated WhatsApp responses to customers.
 */
class SendWhatsAppResponseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [2, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Conversation $conversation,
        public string $content,
        public ?int $replyToMessageId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ConversationService $conversationService): Message
    {
        $contact = $this->conversation->contact;

        Log::info("Dispatching queued AI WhatsApp response to conversation #{$this->conversation->id}", [
            'conversation_id' => $this->conversation->id,
            'contact_id' => $contact?->id,
            'phone' => $contact?->phone,
            'length' => mb_strlen($this->content),
        ]);

        return $conversationService->sendOutboundMessage(
            conversation: $this->conversation,
            body: $this->content,
            sender: null, // System / AI Agent
            type: 'text'
        );
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("Failed to dispatch queued WhatsApp response to conversation #{$this->conversation->id}: {$exception->getMessage()}", [
            'conversation_id' => $this->conversation->id,
            'exception' => $exception,
        ]);
    }
}
