<?php

namespace App\Services\Email;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Enums\EmailMarketingStatus;
use App\Enums\EmailMessageType;
use App\Enums\EmailSuppressionReason;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Models\SmartList;
use App\Models\User;
use App\Services\SmartList\SmartListQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Enterprise CRM Email Marketing Campaign Engine.
 *
 * Coordinates audience segmentation, compliance verification (suppression, consent,
 * bounces, complaints), queued batch sending, lifecycle transitions, and live metrics.
 */
class EmailCampaignService
{
    public function __construct(
        protected EmailTemplateService $templateService,
        protected EmailService $emailService,
        protected EmailSuppressionService $suppressionService,
        protected SmartListQueryBuilder $smartListQueryBuilder
    ) {}

    /**
     * Build dynamic contact query from Smart List and/or segment criteria.
     *
     * @param  array<string, mixed>|null  $segmentCriteria
     * @return Builder<Contact>
     */
    public function buildAudienceQuery(?int $smartListId = null, ?array $segmentCriteria = null): Builder
    {
        $query = Contact::query()->select('contacts.*')->distinct();

        // 1. Inherit from Smart List if provided
        if (! empty($smartListId)) {
            $smartList = SmartList::find($smartListId);
            if ($smartList) {
                $query = $smartList->contactsQuery();
            }
        }

        // 2. Apply additional or override segment criteria
        if (! empty($segmentCriteria) && ! empty($segmentCriteria['rules'])) {
            $this->smartListQueryBuilder->applyRuleGroup($query, $segmentCriteria, 'and');
        }

        return $query;
    }

    /**
     * Preview matched audience, calculating eligible vs suppressed/skipped contacts.
     *
     * @param  array<string, mixed>|null  $segmentCriteria
     * @return array{
     *     total_matching: int,
     *     eligible_count: int,
     *     skipped_count: int,
     *     skipped_breakdown: array<string, int>,
     *     sample_contacts: list<array<string, mixed>>
     * }
     */
    public function previewAudience(?int $smartListId = null, ?array $segmentCriteria = null): array
    {
        $query = $this->buildAudienceQuery($smartListId, $segmentCriteria);

        $totalMatching = 0;
        $eligibleCount = 0;
        $skippedCount = 0;
        $skippedBreakdown = [
            'invalid_email' => 0,
            'not_subscribed' => 0,
            'unsubscribed' => 0,
            'suppressed' => 0,
            'hard_bounced' => 0,
            'complained' => 0,
        ];
        $sampleContacts = [];

        $query->chunk(250, function ($contacts) use (
            &$totalMatching,
            &$eligibleCount,
            &$skippedCount,
            &$skippedBreakdown,
            &$sampleContacts
        ): void {
            foreach ($contacts as $contact) {
                $totalMatching++;
                $check = $this->verifyContactEligibility($contact);

                if ($check['eligible']) {
                    $eligibleCount++;
                } else {
                    $skippedCount++;
                    $reason = $check['reason'] ?? 'not_subscribed';
                    if (isset($skippedBreakdown[$reason])) {
                        $skippedBreakdown[$reason]++;
                    } else {
                        $skippedBreakdown['not_subscribed']++;
                    }
                }

                if (count($sampleContacts) < 10) {
                    $sampleContacts[] = [
                        'id' => $contact->id,
                        'name' => $contact->full_name,
                        'email' => $contact->email,
                        'eligible' => $check['eligible'],
                        'reason' => $check['reason'],
                        'reason_label' => $check['reason_label'],
                        'marketing_status' => $contact->email_marketing_status?->value ?? 'pending_consent',
                    ];
                }
            }
        });

        return [
            'total_matching' => $totalMatching,
            'eligible_count' => $eligibleCount,
            'skipped_count' => $skippedCount,
            'skipped_breakdown' => $skippedBreakdown,
            'sample_contacts' => $sampleContacts,
        ];
    }

    /**
     * Verify whether a contact is eligible to receive marketing emails.
     *
     * @return array{eligible: bool, reason: ?string, reason_label: ?string}
     */
    public function verifyContactEligibility(Contact $contact): array
    {
        $email = strtolower(trim((string) $contact->email));

        // 1. Valid email check
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'eligible' => false,
                'reason' => 'invalid_email',
                'reason_label' => 'Invalid or missing email address',
            ];
        }

        // 2. Unsubscribed check
        if ($contact->has_opted_out || $contact->email_marketing_status === EmailMarketingStatus::Unsubscribed) {
            return [
                'eligible' => false,
                'reason' => 'unsubscribed',
                'reason_label' => 'Contact opted out or unsubscribed',
            ];
        }

        // 3. Hard bounced check
        if ($contact->email_marketing_status === EmailMarketingStatus::Bounced ||
            EmailSuppression::forEmail($email)->where('reason', EmailSuppressionReason::Bounce)->exists()) {
            return [
                'eligible' => false,
                'reason' => 'hard_bounced',
                'reason_label' => 'Address previously hard-bounced',
            ];
        }

        // 4. Spam Complaint check
        if (EmailSuppression::forEmail($email)->where('reason', EmailSuppressionReason::Complaint)->exists()) {
            return [
                'eligible' => false,
                'reason' => 'complained',
                'reason_label' => 'Address previously logged spam complaint',
            ];
        }

        // 5. Active suppression check
        if ($this->suppressionService->isSuppressed($email)) {
            return [
                'eligible' => false,
                'reason' => 'suppressed',
                'reason_label' => 'Suppressed in CRM email suppression ledger',
            ];
        }

        // 6. Marketing subscription consent permission
        if ($contact->email_marketing_status !== EmailMarketingStatus::Subscribed) {
            return [
                'eligible' => false,
                'reason' => 'not_subscribed',
                'reason_label' => 'Awaiting double opt-in / marketing consent',
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
            'reason_label' => null,
        ];
    }

    /**
     * Resolve and snapshot campaign recipients into email_campaign_recipients table.
     */
    public function resolveRecipients(EmailCampaign $campaign): int
    {
        $campaign->update(['status' => EmailCampaignStatus::Processing]);

        // Clear existing recipients if this campaign is being re-resolved
        $campaign->recipients()->delete();

        $query = $this->buildAudienceQuery($campaign->smart_list_id, $campaign->segment_criteria);

        $totalMatching = 0;
        $eligibleCount = 0;
        $skippedCount = 0;
        $batchSize = max(10, $campaign->batch_size ?: 50);
        $currentBatchNumber = 1;
        $currentBatchCount = 0;

        $query->chunk(250, function ($contacts) use (
            $campaign,
            $batchSize,
            &$totalMatching,
            &$eligibleCount,
            &$skippedCount,
            &$currentBatchNumber,
            &$currentBatchCount
        ): void {
            $recordsToInsert = [];

            foreach ($contacts as $contact) {
                $totalMatching++;
                $check = $this->verifyContactEligibility($contact);

                if ($check['eligible']) {
                    $eligibleCount++;
                    $currentBatchCount++;
                    if ($currentBatchCount > $batchSize) {
                        $currentBatchNumber++;
                        $currentBatchCount = 1;
                    }

                    $recordsToInsert[] = [
                        'email_campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'email' => strtolower(trim((string) $contact->email)),
                        'status' => EmailCampaignRecipientStatus::Pending->value,
                        'skip_reason' => null,
                        'batch_number' => $currentBatchNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    $skippedCount++;
                    $recordsToInsert[] = [
                        'email_campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'email' => strtolower(trim((string) ($contact->email ?? "contact-{$contact->id}@invalid.bamcomcrm.internal"))),
                        'status' => EmailCampaignRecipientStatus::Skipped->value,
                        'skip_reason' => $check['reason'],
                        'batch_number' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($recordsToInsert)) {
                EmailCampaignRecipient::insert($recordsToInsert);
            }
        });

        $campaign->update([
            'total_recipients' => $totalMatching,
            'eligible_recipients' => $eligibleCount,
            'skipped_recipients' => $skippedCount,
            'status' => $eligibleCount > 0 ? EmailCampaignStatus::Draft : EmailCampaignStatus::Completed,
        ]);

        return $eligibleCount;
    }

    /**
     * Dispatch campaign sending across queued batches without blocking the web request.
     *
     * @throws ValidationException
     */
    public function dispatchCampaign(EmailCampaign $campaign): void
    {
        if (! in_array($campaign->status, [
            EmailCampaignStatus::Draft,
            EmailCampaignStatus::Scheduled,
            EmailCampaignStatus::Paused,
        ], true)) {
            throw ValidationException::withMessages([
                'campaign' => ["Campaign in status '{$campaign->status->label()}' cannot be dispatched."],
            ]);
        }

        // If recipients haven't been resolved yet, resolve them now
        if ($campaign->recipients()->count() === 0) {
            $eligible = $this->resolveRecipients($campaign);
            if ($eligible === 0) {
                $campaign->update([
                    'status' => EmailCampaignStatus::Completed,
                    'started_at' => now(),
                    'completed_at' => now(),
                ]);

                return;
            }
        }

        // Mark as sending
        $campaign->update([
            'status' => EmailCampaignStatus::Sending,
            'started_at' => $campaign->started_at ?? now(),
            'paused_at' => null,
        ]);

        // Retrieve all pending recipients grouped by batch_number
        $pendingRecipients = $campaign->recipients()
            ->where('status', EmailCampaignRecipientStatus::Pending->value)
            ->where('batch_number', '>', 0)
            ->get(['id', 'batch_number']);

        if ($pendingRecipients->isEmpty()) {
            $campaign->update([
                'status' => EmailCampaignStatus::Completed,
                'completed_at' => now(),
            ]);

            return;
        }

        $batches = $pendingRecipients->groupBy('batch_number');

        foreach ($batches as $batchNumber => $batchRecipients) {
            $recipientIds = $batchRecipients->pluck('id')->all();
            SendCampaignBatchJob::dispatch($campaign->id, $recipientIds, (int) $batchNumber)->onQueue('emails');
        }
    }

    /**
     * Pause an actively sending or processing campaign.
     *
     * @throws ValidationException
     */
    public function pauseCampaign(EmailCampaign $campaign): void
    {
        if (! $campaign->status->canBePaused()) {
            throw ValidationException::withMessages([
                'campaign' => ["Only sending or processing campaigns can be paused. Current status: {$campaign->status->label()}."],
            ]);
        }

        $campaign->update([
            'status' => EmailCampaignStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    /**
     * Resume a paused campaign.
     *
     * @throws ValidationException
     */
    public function resumeCampaign(EmailCampaign $campaign): void
    {
        if (! $campaign->status->canBeResumed()) {
            throw ValidationException::withMessages([
                'campaign' => ["Only paused campaigns can be resumed. Current status: {$campaign->status->label()}."],
            ]);
        }

        $this->dispatchCampaign($campaign);
    }

    /**
     * Cancel a campaign and mark remaining pending recipients as skipped.
     *
     * @throws ValidationException
     */
    public function cancelCampaign(EmailCampaign $campaign, ?string $reason = null): void
    {
        if (! $campaign->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'campaign' => ["Campaign in status '{$campaign->status->label()}' cannot be cancelled."],
            ]);
        }

        $campaign->update([
            'status' => EmailCampaignStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason ?? 'Cancelled by user',
        ]);

        // Mark remaining pending recipients as skipped
        $campaign->recipients()
            ->where('status', EmailCampaignRecipientStatus::Pending->value)
            ->update([
                'status' => EmailCampaignRecipientStatus::Skipped->value,
                'skip_reason' => 'campaign_cancelled',
            ]);
    }

    /**
     * Schedule a campaign for automated future dispatch.
     *
     * @throws ValidationException
     */
    public function scheduleCampaign(EmailCampaign $campaign, Carbon $scheduledAt): void
    {
        if (! $campaign->status->canBeScheduled()) {
            throw ValidationException::withMessages([
                'campaign' => ["Campaign in status '{$campaign->status->label()}' cannot be scheduled."],
            ]);
        }

        if ($scheduledAt->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['The scheduled time must be in the future.'],
            ]);
        }

        // Snapshot recipients before scheduling
        $this->resolveRecipients($campaign);

        $campaign->update([
            'status' => EmailCampaignStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Send a single preview test email without modifying campaign records.
     */
    public function sendTest(EmailCampaign $campaign, string $testEmail, User $sender): EmailMessage
    {
        $sampleVariables = $this->templateService->generateSampleVariables();

        $rendered = $this->templateService->render(
            htmlTemplate: $campaign->body_html,
            plainTemplate: $campaign->body_plain,
            variables: $sampleVariables,
            preheader: $campaign->preheader,
            wrapWithBrand: true
        );

        $subject = '[TEST CAMPAIGN] '.$this->templateService->renderSubject($campaign->subject, $sampleVariables);

        return $this->emailService->send([
            'to_email' => $testEmail,
            'subject' => $subject,
            'body_html' => $rendered['html'],
            'body_plain' => $rendered['plain'],
            'type' => EmailMessageType::Test,
            'email_template_id' => $campaign->email_template_id,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'campaign_uuid' => $campaign->uuid,
                'is_test_send' => true,
            ],
        ], $sender);
    }
}
