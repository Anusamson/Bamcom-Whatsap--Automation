<?php

namespace App\Jobs;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Enums\EmailMessageType;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignMessage;
use App\Models\EmailCampaignRecipient;
use App\Services\Email\EmailCampaignService;
use App\Services\Email\EmailService;
use App\Services\Email\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Process a discrete queued batch of campaign recipients.
 *
 * Prevents memory exhaustion and enables graceful pause, resume,
 * and cancellation mid-campaign without blocking HTTP requests.
 */
class SendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  list<int>  $recipientIds
     */
    public function __construct(
        public int $campaignId,
        public array $recipientIds,
        public int $batchNumber
    ) {
        $this->queue = 'emails';
    }

    /**
     * Execute the batch sending job.
     */
    public function handle(
        EmailCampaignService $campaignService,
        EmailTemplateService $templateService,
        EmailService $emailService
    ): void {
        $campaign = EmailCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning("[SendCampaignBatchJob] Campaign {$this->campaignId} not found.");

            return;
        }

        // Halt if campaign is paused or cancelled
        if (in_array($campaign->status, [EmailCampaignStatus::Paused, EmailCampaignStatus::Cancelled], true)) {
            Log::info("[SendCampaignBatchJob] Campaign {$campaign->id} is {$campaign->status->value}. Skipping batch {$this->batchNumber}.");

            return;
        }

        $recipients = EmailCampaignRecipient::with(['contact', 'campaign.creator'])
            ->whereIn('id', $this->recipientIds)
            ->where('status', EmailCampaignRecipientStatus::Pending)
            ->get();

        foreach ($recipients as $recipient) {
            // Live pause/cancel check
            $freshCampaign = $campaign->fresh();
            if (! $freshCampaign || in_array($freshCampaign->status, [EmailCampaignStatus::Paused, EmailCampaignStatus::Cancelled], true)) {
                Log::info("[SendCampaignBatchJob] Campaign {$campaign->id} halted mid-batch.");
                break;
            }

            $contact = $recipient->contact;

            // Re-verify recipient eligibility just before sending
            $eligibility = $campaignService->verifyContactEligibility($contact);
            if (! $eligibility['eligible']) {
                $recipient->update([
                    'status' => EmailCampaignRecipientStatus::Skipped,
                    'skip_reason' => $eligibility['reason'],
                ]);
                $campaign->increment('skipped_recipients');

                continue;
            }

            try {
                // Build personalized contact variables
                $variables = $templateService->buildVariables(
                    contact: $contact,
                    agent: $contact->assignedUser ?? $campaign->creator
                );

                // Render dynamic content with safe sanitization and Bamcom branding
                $rendered = $templateService->render(
                    htmlTemplate: $campaign->body_html,
                    plainTemplate: $campaign->body_plain,
                    variables: $variables,
                    preheader: $campaign->preheader,
                    wrapWithBrand: true
                );

                $subject = $templateService->renderSubject($campaign->subject, $variables);

                // Send via EmailService
                $message = $emailService->send([
                    'to_email' => $recipient->email,
                    'subject' => $subject,
                    'body_html' => $rendered['html'],
                    'body_plain' => $rendered['plain'],
                    'type' => EmailMessageType::Marketing,
                    'contact_id' => $contact->id,
                    'email_template_id' => $campaign->email_template_id,
                    'metadata' => [
                        'campaign_id' => $campaign->id,
                        'campaign_uuid' => $campaign->uuid,
                        'recipient_id' => $recipient->id,
                        'batch_number' => $this->batchNumber,
                    ],
                ], $campaign->creator);

                // Update recipient record
                $recipient->update([
                    'status' => EmailCampaignRecipientStatus::Sent,
                    'email_message_id' => $message->id,
                    'sent_at' => now(),
                ]);

                // Record pivot tracking
                EmailCampaignMessage::firstOrCreate([
                    'email_campaign_id' => $campaign->id,
                    'email_campaign_recipient_id' => $recipient->id,
                    'email_message_id' => $message->id,
                ]);

                $campaign->increment('sent_count');
            } catch (Throwable $e) {
                Log::error("[SendCampaignBatchJob] Failed sending to recipient {$recipient->id}: ".$e->getMessage());

                $recipient->update([
                    'status' => EmailCampaignRecipientStatus::Failed,
                    'failed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);

                $campaign->increment('failed_count');
            }
        }

        // Check if all recipients for this campaign have completed
        $remainingPending = $campaign->recipients()
            ->where('status', EmailCampaignRecipientStatus::Pending)
            ->count();

        if ($remainingPending === 0) {
            $fresh = $campaign->fresh();
            if ($fresh && in_array($fresh->status, [EmailCampaignStatus::Sending, EmailCampaignStatus::Processing], true)) {
                $fresh->update([
                    'status' => EmailCampaignStatus::Completed,
                    'completed_at' => now(),
                ]);
            }
        }
    }
}
