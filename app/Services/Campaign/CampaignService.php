<?php

namespace App\Services\Campaign;

use App\Enums\CampaignEventType;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\CampaignMessage;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppMessageService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CampaignService
{
    public function __construct(
        protected AudienceSegmentationService $segmentationService,
        protected WhatsAppMessageService $whatsAppService
    ) {}

    /**
     * Create an audience.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAudience(array $data, ?User $creator = null): Audience
    {
        $filters = (array) ($data['filters'] ?? []);
        $count = $this->segmentationService->getCount($filters);

        return Audience::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'filters' => $filters,
            'cached_count' => $count,
            'created_by_user_id' => $creator?->id,
        ]);
    }

    /**
     * Update an audience.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAudience(Audience $audience, array $data): Audience
    {
        $filters = isset($data['filters']) ? (array) $data['filters'] : (array) $audience->filters;
        $count = $this->segmentationService->getCount($filters);

        $audience->update([
            'name' => $data['name'] ?? $audience->name,
            'description' => $data['description'] ?? $audience->description,
            'filters' => $filters,
            'cached_count' => $count,
        ]);

        return $audience;
    }

    /**
     * Delete an audience.
     */
    public function deleteAudience(Audience $audience): bool
    {
        return (bool) $audience->delete();
    }

    /**
     * Create a campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCampaign(array $data, ?User $creator = null): Campaign
    {
        return DB::transaction(function () use ($data, $creator): Campaign {
            $status = isset($data['status'])
                ? ($data['status'] instanceof CampaignStatus ? $data['status'] : CampaignStatus::from((string) $data['status']))
                : CampaignStatus::Draft;

            $scheduledAt = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
            if ($scheduledAt && $status === CampaignStatus::Draft) {
                $status = CampaignStatus::Scheduled;
            }

            $campaign = Campaign::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $status,
                'audience_id' => $data['audience_id'] ?? null,
                'whatsapp_template_id' => $data['whatsapp_template_id'] ?? null,
                'message_type' => $data['message_type'] ?? 'template',
                'message_content' => $data['message_content'] ?? null,
                'template_parameters' => $data['template_parameters'] ?? null,
                'batch_size' => (int) ($data['batch_size'] ?? 50),
                'batch_delay_seconds' => (int) ($data['batch_delay_seconds'] ?? 5),
                'scheduled_at' => $scheduledAt,
                'created_by_user_id' => $creator?->id,
            ]);

            $this->logEvent($campaign, CampaignEventType::Created, "Campaign '{$campaign->name}' created.");

            return $campaign;
        });
    }

    /**
     * Update an existing campaign.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCampaign(Campaign $campaign, array $data): Campaign
    {
        if ($campaign->isRunning()) {
            throw new InvalidArgumentException('Cannot edit a campaign that is currently running.');
        }

        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
            if ($data['scheduled_at'] && $campaign->status === CampaignStatus::Draft) {
                $data['status'] = CampaignStatus::Scheduled;
            }
        }

        $campaign->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? null,
            'audience_id' => $data['audience_id'] ?? null,
            'whatsapp_template_id' => $data['whatsapp_template_id'] ?? null,
            'message_type' => $data['message_type'] ?? null,
            'message_content' => $data['message_content'] ?? null,
            'template_parameters' => $data['template_parameters'] ?? null,
            'batch_size' => isset($data['batch_size']) ? (int) $data['batch_size'] : null,
            'batch_delay_seconds' => isset($data['batch_delay_seconds']) ? (int) $data['batch_delay_seconds'] : null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ], fn ($val) => ! is_null($val)));

        return $campaign;
    }

    /**
     * Delete campaign.
     */
    public function deleteCampaign(Campaign $campaign): bool
    {
        return (bool) $campaign->delete();
    }

    /**
     * Schedule a campaign.
     */
    public function scheduleCampaign(Campaign $campaign, Carbon|string $scheduledAt): Campaign
    {
        $dt = Carbon::parse($scheduledAt);

        $campaign->update([
            'status' => CampaignStatus::Scheduled,
            'scheduled_at' => $dt,
        ]);

        $this->logEvent($campaign, CampaignEventType::Scheduled, "Campaign scheduled for {$dt->toIso8601String()}.");

        return $campaign;
    }

    /**
     * Launch/Start campaign execution: resolves audience, creates recipients, dispatches first batch.
     */
    public function launchCampaign(Campaign $campaign): Campaign
    {
        if (! $campaign->status->canBeStarted()) {
            throw new InvalidArgumentException("Campaign cannot be launched from current status ({$campaign->status->label()}).");
        }

        if (! $campaign->audience && empty($campaign->audience_id)) {
            throw new InvalidArgumentException('Campaign must have an audience assigned to launch.');
        }

        return DB::transaction(function () use ($campaign): Campaign {
            $audience = $campaign->audience;
            $contacts = $this->segmentationService->resolveAudienceContacts($audience);

            // Filter out opted-out contacts or contacts without phones
            $eligibleContacts = $contacts->filter(function (Contact $c): bool {
                return ! $c->has_opted_out && ! empty($c->phone);
            });

            // Delete any existing un-sent recipients from previous drafts
            $campaign->recipients()->where('status', CampaignRecipientStatus::Pending->value)->delete();

            $batchSize = max(1, $campaign->batch_size);
            $batchNumber = 1;
            $recipientCount = 0;

            foreach ($eligibleContacts->chunk($batchSize) as $chunk) {
                foreach ($chunk as $contact) {
                    $lead = $contact->leads->first();

                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'lead_id' => $lead?->id,
                        'phone' => $contact->phone,
                        'status' => CampaignRecipientStatus::Pending,
                        'batch_number' => $batchNumber,
                    ]);

                    $recipientCount++;
                }
                $batchNumber++;
            }

            $campaign->update([
                'status' => CampaignStatus::Running,
                'started_at' => now(),
                'total_recipients' => $recipientCount,
                'processed_recipients' => 0,
                'sent_count' => 0,
                'delivered_count' => 0,
                'read_count' => 0,
                'failed_count' => 0,
                'opted_out_count' => 0,
            ]);

            $this->logEvent(
                $campaign,
                CampaignEventType::Started,
                "Campaign launched with {$recipientCount} eligible recipients across ".($batchNumber - 1).' batches.'
            );

            if ($recipientCount > 0) {
                // Dispatch first batch immediately
                SendCampaignBatchJob::dispatch($campaign->id, 1);
            } else {
                // No recipients, mark completed immediately
                $campaign->update([
                    'status' => CampaignStatus::Completed,
                    'completed_at' => now(),
                ]);
                $this->logEvent($campaign, CampaignEventType::Completed, 'Campaign completed immediately (no recipients).');
            }

            return $campaign;
        });
    }

    /**
     * Pause a running campaign.
     */
    public function pauseCampaign(Campaign $campaign): Campaign
    {
        if (! $campaign->status->canBePaused()) {
            throw new InvalidArgumentException('Only running campaigns can be paused.');
        }

        $campaign->update([
            'status' => CampaignStatus::Paused,
            'paused_at' => now(),
        ]);

        $this->logEvent($campaign, CampaignEventType::Paused, 'Campaign was paused.');

        return $campaign;
    }

    /**
     * Resume a paused campaign.
     */
    public function resumeCampaign(Campaign $campaign): Campaign
    {
        if (! $campaign->status->canBeResumed()) {
            throw new InvalidArgumentException('Only paused campaigns can be resumed.');
        }

        $campaign->update([
            'status' => CampaignStatus::Running,
            'paused_at' => null,
        ]);

        $this->logEvent($campaign, CampaignEventType::Resumed, 'Campaign was resumed.');

        $nextBatchNumber = $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->min('batch_number');

        if ($nextBatchNumber) {
            SendCampaignBatchJob::dispatch($campaign->id, (int) $nextBatchNumber);
        } else {
            $campaign->update([
                'status' => CampaignStatus::Completed,
                'completed_at' => now(),
            ]);
            $this->logEvent($campaign, CampaignEventType::Completed, 'Campaign completed.');
        }

        return $campaign;
    }

    /**
     * Cancel a campaign.
     */
    public function cancelCampaign(Campaign $campaign, string $reason = 'Cancelled by user'): Campaign
    {
        if (! $campaign->status->canBeCancelled()) {
            throw new InvalidArgumentException('Campaign is already finished or cannot be cancelled.');
        }

        return DB::transaction(function () use ($campaign, $reason): Campaign {
            $campaign->update([
                'status' => CampaignStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Mark any pending or queued recipients as skipped
            $campaign->recipients()
                ->whereIn('status', [CampaignRecipientStatus::Pending->value, CampaignRecipientStatus::Queued->value])
                ->update([
                    'status' => CampaignRecipientStatus::Skipped->value,
                    'error_message' => "Campaign cancelled: {$reason}",
                ]);

            $this->logEvent($campaign, CampaignEventType::Cancelled, "Campaign cancelled: {$reason}");

            return $campaign;
        });
    }

    /**
     * Process a batch of recipients for a campaign.
     */
    public function processBatch(Campaign $campaign, int $batchNumber): void
    {
        // Guard: check if campaign is still running
        if (! $campaign->isRunning()) {
            Log::info("CampaignService: Campaign #{$campaign->id} is {$campaign->status->value}. Skipping batch #{$batchNumber}.");

            return;
        }

        $recipients = $campaign->recipients()
            ->where('batch_number', $batchNumber)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->with(['contact', 'lead'])
            ->get();

        if ($recipients->isEmpty()) {
            $this->checkAndCompleteOrAdvance($campaign);

            return;
        }

        $this->logEvent(
            $campaign,
            CampaignEventType::BatchDispatched,
            "Processing batch #{$batchNumber} ({$recipients->count()} recipients)."
        );

        foreach ($recipients as $recipient) {
            // Re-check campaign status mid-batch in case user clicked Pause / Cancel
            if ($campaign->fresh()->isPaused() || $campaign->fresh()->isCancelled()) {
                Log::info("CampaignService: Campaign #{$campaign->id} was paused/cancelled mid-batch. Halting batch #{$batchNumber}.");

                return;
            }

            $this->sendToRecipient($campaign, $recipient);
        }

        $this->checkAndCompleteOrAdvance($campaign);
    }

    /**
     * Check if more pending batches exist or mark campaign completed.
     */
    protected function checkAndCompleteOrAdvance(Campaign $campaign): void
    {
        $nextBatchNumber = $campaign->recipients()
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->min('batch_number');

        if ($nextBatchNumber) {
            $delay = max(1, $campaign->batch_delay_seconds);
            SendCampaignBatchJob::dispatch($campaign->id, (int) $nextBatchNumber)
                ->delay(now()->addSeconds($delay));
        } else {
            $campaign->update([
                'status' => CampaignStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->logEvent(
                $campaign,
                CampaignEventType::Completed,
                "Campaign completed successfully. Sent: {$campaign->fresh()->sent_count}, Failed: {$campaign->fresh()->failed_count}, Opted Out: {$campaign->fresh()->opted_out_count}."
            );
        }
    }

    /**
     * Send campaign message to an individual recipient with opt-out guardrail check.
     */
    public function sendToRecipient(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $contact = $recipient->contact;
        $lead = $recipient->lead;

        // 1. Critical Opt-Out Guardrail Check
        if ($contact->has_opted_out) {
            $recipient->update([
                'status' => CampaignRecipientStatus::OptedOut,
                'error_message' => 'Contact has opted out of marketing communications.',
            ]);

            $campaign->increment('opted_out_count');
            $campaign->increment('processed_recipients');

            $this->logEvent(
                $campaign,
                CampaignEventType::OptedOut,
                "Recipient {$recipient->phone} skipped: contact opted out.",
                $recipient
            );

            return;
        }

        try {
            if ($campaign->message_type === 'template' && $campaign->template) {
                $this->sendTemplateMessage($campaign, $recipient, $contact, $lead);
            } else {
                $this->sendTextMessage($campaign, $recipient, $contact, $lead);
            }
        } catch (Exception $e) {
            Log::error("CampaignService: Failed sending to recipient #{$recipient->id} ({$recipient->phone}): {$e->getMessage()}");

            $recipient->update([
                'status' => CampaignRecipientStatus::Failed,
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $campaign->increment('failed_count');
            $campaign->increment('processed_recipients');

            $this->logEvent(
                $campaign,
                CampaignEventType::Failed,
                "Send failed to {$recipient->phone}: {$e->getMessage()}",
                $recipient
            );
        }
    }

    /**
     * Send an approved Meta WhatsApp template to a recipient.
     */
    protected function sendTemplateMessage(
        Campaign $campaign,
        CampaignRecipient $recipient,
        Contact $contact,
        ?Lead $lead
    ): void {
        $template = $campaign->template;
        $rawParams = (array) ($campaign->template_parameters ?? []);

        // Interpolate tokens inside parameters
        $interpolatedParams = array_map(
            fn ($param) => is_string($param) ? $this->interpolateTokens($param, $contact, $lead) : (string) $param,
            $rawParams
        );

        $result = $this->whatsAppService->sendTemplateMessage(
            to: $recipient->phone,
            templateName: $template->name,
            bodyParameters: $interpolatedParams,
            languageCode: $template->language ?: 'en_US',
            contact: $contact
        );

        if (! empty($result['success'])) {
            $metaMessageId = $result['message_id'] ?? null;

            $recipient->update([
                'status' => CampaignRecipientStatus::Sent,
                'meta_message_id' => $metaMessageId,
                'sent_at' => now(),
            ]);

            $campaign->increment('sent_count');
            $campaign->increment('processed_recipients');

            // Find WhatsAppMessage model if created by service
            $waMessage = WhatsAppMessage::where('meta_message_id', $metaMessageId)->first();

            CampaignMessage::create([
                'campaign_id' => $campaign->id,
                'campaign_recipient_id' => $recipient->id,
                'whatsapp_message_id' => $waMessage?->id,
                'direction' => 'outbound',
                'meta_message_id' => $metaMessageId,
                'body' => $template->body_text,
                'template_name' => $template->name,
                'payload' => $result['response'] ?? null,
            ]);

            $this->logEvent(
                $campaign,
                CampaignEventType::Sent,
                "Template '{$template->name}' sent to {$recipient->phone}.",
                $recipient
            );
        } else {
            throw new Exception($result['error'] ?? 'WhatsApp API returned unsuccessful status.');
        }
    }

    /**
     * Send custom text message to recipient.
     */
    protected function sendTextMessage(
        Campaign $campaign,
        CampaignRecipient $recipient,
        Contact $contact,
        ?Lead $lead
    ): void {
        $rawBody = (string) ($campaign->message_content ?? 'Hello from Bamcom Real Estate');
        $body = $this->interpolateTokens($rawBody, $contact, $lead);

        $result = $this->whatsAppService->sendTextMessage(
            to: $recipient->phone,
            body: $body,
            contact: $contact
        );

        if (! empty($result['success'])) {
            $metaMessageId = $result['message_id'] ?? null;

            $recipient->update([
                'status' => CampaignRecipientStatus::Sent,
                'meta_message_id' => $metaMessageId,
                'sent_at' => now(),
            ]);

            $campaign->increment('sent_count');
            $campaign->increment('processed_recipients');

            $waMessage = WhatsAppMessage::where('meta_message_id', $metaMessageId)->first();

            CampaignMessage::create([
                'campaign_id' => $campaign->id,
                'campaign_recipient_id' => $recipient->id,
                'whatsapp_message_id' => $waMessage?->id,
                'direction' => 'outbound',
                'meta_message_id' => $metaMessageId,
                'body' => $body,
                'payload' => $result['response'] ?? null,
            ]);

            $this->logEvent(
                $campaign,
                CampaignEventType::Sent,
                "Text message sent to {$recipient->phone}.",
                $recipient
            );
        } else {
            throw new Exception($result['error'] ?? 'WhatsApp API returned unsuccessful status.');
        }
    }

    /**
     * Interpolate merge tokens like {{contact.first_name}}, {{contact.name}}, {{lead.score}}.
     */
    public function interpolateTokens(string $template, Contact $contact, ?Lead $lead = null): string
    {
        $tokens = [
            '{{contact.first_name}}' => $contact->first_name ?: 'Client',
            '{{contact.last_name}}' => $contact->last_name ?: '',
            '{{contact.name}}' => $contact->full_name ?: 'Client',
            '{{contact.phone}}' => $contact->phone ?: '',
            '{{contact.location}}' => $contact->location ?: 'Nigeria',
            '{{lead.score}}' => (string) ($lead?->score ?? '50'),
            '{{lead.temperature}}' => (string) ($lead?->temperature instanceof \BackedEnum ? $lead->temperature->value : ($lead?->temperature ?? 'warm')),
            '{{lead.title}}' => $lead?->title ?: '',
        ];

        return strtr($template, $tokens);
    }

    /**
     * Log a campaign audit event.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function logEvent(
        Campaign $campaign,
        CampaignEventType $eventType,
        ?string $description = null,
        ?CampaignRecipient $recipient = null,
        ?array $data = null
    ): CampaignEvent {
        return CampaignEvent::create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient?->id,
            'event_type' => $eventType,
            'description' => $description,
            'data' => $data,
            'created_at' => now(),
        ]);
    }
}
