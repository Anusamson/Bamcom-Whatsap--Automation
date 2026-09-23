<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ConversationAiPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued job for processing an incoming customer message through the AI Sales Pipeline.
 */
class ProcessConversationAiTurn implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5];

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public Conversation $conversation,
        public Message $message,
        public array $options = []
    ) {}

    /**
     * Execute the job.
     *
     * @return array{status: string, mode: string, action_taken: string, response?: ?string, error?: ?string}
     */
    public function handle(ConversationAiPipeline $pipeline): array
    {
        return $pipeline->processTurn($this->conversation, $this->message, $this->options);
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessConversationAiTurn job failed for conversation #{$this->conversation->id}: {$exception->getMessage()}", [
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'exception' => $exception,
        ]);
    }
}
