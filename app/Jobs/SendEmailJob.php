<?php

namespace App\Jobs;

use App\Enums\EmailMessageStatus;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Services\Email\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asynchronous Worker Job for Delivering Outbound Emails via Amazon SES.
 */
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $emailMessageId) {}

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        $message = EmailMessage::find($this->emailMessageId);

        if (! $message) {
            Log::warning("SendEmailJob: EmailMessage [{$this->emailMessageId}] not found.");

            return;
        }

        // Do not re-send already delivered or bounced messages
        if (in_array($message->status, [EmailMessageStatus::Sent, EmailMessageStatus::Delivered], true)) {
            Log::info("SendEmailJob: EmailMessage [{$this->emailMessageId}] already delivered.");

            return;
        }

        $emailService->deliverQueuedMessage($message);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error("SendEmailJob [FAILED]: EmailMessage [{$this->emailMessageId}] failed permanently", [
            'error' => $exception?->getMessage(),
        ]);

        $message = EmailMessage::find($this->emailMessageId);
        if ($message) {
            $message->update([
                'status' => EmailMessageStatus::Failed,
                'error_message' => $exception?->getMessage() ?? 'Max queue attempts exceeded',
            ]);

            EmailEvent::create([
                'email_message_id' => $message->id,
                'event_type' => 'failure',
                'payload' => [
                    'error' => $exception?->getMessage(),
                    'trace' => $exception?->getTraceAsString(),
                ],
                'occurred_at' => now(),
            ]);
        }
    }
}
