<?php

namespace App\Services\Email;

use App\Enums\EmailMarketingStatus;
use App\Enums\EmailSuppressionReason;
use App\Models\Contact;
use App\Models\EmailSuppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Email Suppression and Compliance Service.
 *
 * Enforces CAN-SPAM, GDPR, and AWS SES sending reputation compliance by managing
 * hard bounces, spam complaints, and opt-outs across the authoritative contacts database.
 */
class EmailSuppressionService
{
    /**
     * Check if an email address is actively suppressed from receiving marketing emails.
     */
    public function isSuppressed(string $email): bool
    {
        $normalized = strtolower(trim($email));

        if (empty($normalized)) {
            return true;
        }

        // 1. Check dedicated suppression ledger
        if (EmailSuppression::forEmail($normalized)->exists()) {
            return true;
        }

        // 2. Check authoritative contacts table
        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();
        if ($contact) {
            if (in_array($contact->email_marketing_status, [
                EmailMarketingStatus::Bounced,
                EmailMarketingStatus::Suppressed,
            ], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a contact can receive marketing email specifically.
     */
    public function canReceiveMarketing(string $email): bool
    {
        $normalized = strtolower(trim($email));

        if ($this->isSuppressed($normalized)) {
            return false;
        }

        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();
        if ($contact) {
            return $contact->email_marketing_status === EmailMarketingStatus::Subscribed
                && ! $contact->has_opted_out;
        }

        // Unregistered address cannot receive marketing broadcasts without opt-in consent
        return false;
    }

    /**
     * Suppress an email address across the ledger and authoritative contacts table.
     */
    public function suppress(
        string $email,
        EmailSuppressionReason|string $reason,
        ?string $details = null,
        ?int $contactId = null,
        ?int $userId = null
    ): EmailSuppression {
        $normalized = strtolower(trim($email));
        $reasonEnum = $reason instanceof EmailSuppressionReason
            ? $reason
            : (EmailSuppressionReason::tryFrom($reason) ?? EmailSuppressionReason::Manual);

        // Find associated contact if not explicitly supplied
        $contact = $contactId ? Contact::find($contactId) : Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();

        // 1. Upsert into suppression ledger
        $suppression = EmailSuppression::updateOrCreate(
            ['email' => $normalized],
            [
                'contact_id' => $contact?->id,
                'reason' => $reasonEnum,
                'details' => $details,
                'suppressed_at' => Carbon::now(),
                'created_by' => $userId,
            ]
        );

        // 2. Synchronize with authoritative Contact record
        if ($contact) {
            $contactUpdate = [];

            if ($reasonEnum === EmailSuppressionReason::Bounce) {
                $contactUpdate['email_marketing_status'] = EmailMarketingStatus::Bounced;
                $contactUpdate['email_bounced_at'] = Carbon::now();
                $contactUpdate['email_bounce_count'] = max(1, ($contact->email_bounce_count ?? 0));
            } elseif ($reasonEnum === EmailSuppressionReason::Unsubscribe) {
                $contactUpdate['email_marketing_status'] = EmailMarketingStatus::Unsubscribed;
                $contactUpdate['email_unsubscribed_at'] = Carbon::now();
                $contactUpdate['has_opted_out'] = true;
                $contactUpdate['opted_out_at'] = Carbon::now();
                $contactUpdate['opt_out_reason'] = $details ?? 'Email unsubscribed';
            } else {
                $contactUpdate['email_marketing_status'] = EmailMarketingStatus::Suppressed;
            }

            $contact->update($contactUpdate);
        }

        Log::info('EmailSuppressionService: Suppressed email', [
            'email' => $normalized,
            'reason' => $reasonEnum->value,
            'contact_id' => $contact?->id,
        ]);

        return $suppression;
    }

    /**
     * Remove an email from the suppression ledger.
     */
    public function unsuppress(string $email, ?int $restoredStatusUserId = null): bool
    {
        $normalized = strtolower(trim($email));

        $deleted = EmailSuppression::where('email', $normalized)->delete();

        // Re-enable contact marketing status to pending consent or subscribed if previously suppressed
        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();
        if ($contact && in_array($contact->email_marketing_status, [
            EmailMarketingStatus::Suppressed,
            EmailMarketingStatus::Bounced,
        ], true)) {
            $contact->update([
                'email_marketing_status' => EmailMarketingStatus::PendingConsent,
                'email_bounce_count' => 0,
            ]);
        }

        return $deleted > 0;
    }

    /**
     * Process an automated delivery bounce from Amazon SES.
     */
    public function recordBounce(string $email, string $bounceType = 'Permanent', ?string $diagnostic = null): void
    {
        $normalized = strtolower(trim($email));
        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();

        $newBounceCount = ($contact?->email_bounce_count ?? 0) + 1;
        if ($contact) {
            $contact->update([
                'email_bounce_count' => $newBounceCount,
                'email_bounced_at' => Carbon::now(),
            ]);
        }

        // Hard bounce (Permanent) or repetitive soft bounces triggers immediate suppression
        if (strcasecmp($bounceType, 'Permanent') === 0 || $newBounceCount >= 3) {
            $this->suppress(
                email: $normalized,
                reason: EmailSuppressionReason::Bounce,
                details: "SES Bounce Type: {$bounceType}. ".($diagnostic ?? ''),
                contactId: $contact?->id
            );
        }
    }

    /**
     * Process an automated spam complaint from Amazon SES.
     */
    public function recordComplaint(string $email, ?string $feedbackType = null): void
    {
        $normalized = strtolower(trim($email));
        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();

        $this->suppress(
            email: $normalized,
            reason: EmailSuppressionReason::Complaint,
            details: 'SES Spam Complaint feedback: '.($feedbackType ?? 'abuse'),
            contactId: $contact?->id
        );
    }

    /**
     * Process an opt-out unsubscribe request.
     */
    public function recordUnsubscribe(string $email, ?string $reason = null): void
    {
        $normalized = strtolower(trim($email));
        $contact = Contact::whereRaw('LOWER(email) = ?', [$normalized])->first();

        $this->suppress(
            email: $normalized,
            reason: EmailSuppressionReason::Unsubscribe,
            details: $reason ?? 'Recipient clicked unsubscribe link',
            contactId: $contact?->id
        );
    }
}
