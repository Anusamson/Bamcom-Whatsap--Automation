<?php

namespace App\Services\Email;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use App\Jobs\SendEmailJob;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\Contracts\EmailProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Enterprise Email Dispatcher & Lifecycle Orchestrator for Bamcom AI CRM.
 *
 * Integrates directly with the authoritative Contacts database, handles
 * transactional & marketing compliance, executes queued delivery via SES,
 * and maintains complete audit trails.
 */
class EmailService
{
    public function __construct(
        protected EmailProviderInterface $provider,
        protected EmailSuppressionService $suppressionService,
        protected EmailTemplateService $templateService
    ) {}

    /**
     * Queue an outbound email for delivery.
     *
     * @param array{
     *     to_email: string,
     *     to_name?: ?string,
     *     subject?: ?string,
     *     body_html?: ?string,
     *     body_plain?: ?string,
     *     email_account_id?: ?int,
     *     contact_id?: ?int,
     *     email_template_id?: ?int,
     *     template_variables?: array<string, mixed>,
     *     type?: EmailMessageType|string,
     *     reply_to_email?: ?string,
     *     metadata?: array<string, mixed>
     * } $data
     *
     * @throws ValidationException
     */
    public function send(array $data, ?User $sender = null): EmailMessage
    {
        $toEmail = strtolower(trim($data['to_email'] ?? ''));
        if (empty($toEmail) || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'to_email' => ['A valid destination email address is required.'],
            ]);
        }

        // Determine Email Type
        $rawType = $data['type'] ?? EmailMessageType::Transactional;
        $type = $rawType instanceof EmailMessageType
            ? $rawType
            : (EmailMessageType::tryFrom((string) $rawType) ?? EmailMessageType::Transactional);

        // Resolve Contact from the authoritative Contacts database
        $contact = null;
        if (! empty($data['contact_id'])) {
            $contact = Contact::find($data['contact_id']);
        }

        if (! $contact) {
            $contact = Contact::whereRaw('LOWER(email) = ?', [$toEmail])->first();
        }

        // Suppression & Consent Compliance
        if ($this->suppressionService->isSuppressed($toEmail)) {
            if ($type === EmailMessageType::Marketing) {
                throw ValidationException::withMessages([
                    'to_email' => ["Recipient {$toEmail} is on the suppression list (unsubscribed or bounced) and cannot receive marketing communications."],
                ]);
            }
        }

        if ($type === EmailMessageType::Marketing) {
            if ($contact && ! $contact->canReceiveMarketingEmail()) {
                throw ValidationException::withMessages([
                    'to_email' => ["Contact has marketing status '{$contact->email_marketing_status?->label()}' and has not consented to marketing broadcasts."],
                ]);
            }
        }

        // Resolve Email Account
        $account = null;
        if (! empty($data['email_account_id'])) {
            $account = EmailAccount::find($data['email_account_id']);
        }
        if (! $account) {
            $account = $this->getDefaultAccount($sender);
        }

        // Resolve Template and Render Content
        $template = null;
        $variables = $data['template_variables'] ?? [];

        if (! empty($data['email_template_id'])) {
            $template = EmailTemplate::find($data['email_template_id']);
            if ($template) {
                $mergedVariables = $this->templateService->buildVariablesForContact($contact, $variables);
                $rendered = $this->templateService->render($template->body_html, $template->body_plain, $mergedVariables);
                $subject = ! empty($data['subject'])
                    ? $this->templateService->renderSubject($data['subject'], $mergedVariables)
                    : $this->templateService->renderSubject($template->subject, $mergedVariables);
                $bodyHtml = $rendered['html'];
                $bodyPlain = $rendered['plain'];
            }
        }

        if (empty($subject)) {
            $subject = $data['subject'] ?? 'Notification from '.config('app.name', 'Bamcom AI CRM');
        }

        if (empty($bodyHtml)) {
            $bodyHtml = $data['body_html'] ?? '<p>No content provided.</p>';
            $bodyPlain = ! empty($data['body_plain'])
                ? $data['body_plain']
                : $this->templateService->convertHtmlToPlainText($bodyHtml);
        }

        $fromEmail = $account->from_email;
        $fromName = $account->from_name;
        $replyTo = $data['reply_to_email'] ?? $account->reply_to_email;

        // Persist Queued Message
        $message = EmailMessage::create([
            'email_account_id' => $account->id,
            'contact_id' => $contact?->id,
            'user_id' => $sender?->id,
            'email_template_id' => $template?->id,
            'to_email' => $toEmail,
            'to_name' => $data['to_name'] ?? $contact?->full_name,
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to_email' => $replyTo,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain,
            'type' => $type,
            'status' => EmailMessageStatus::Queued,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Record Initial Event
        EmailEvent::create([
            'email_message_id' => $message->id,
            'event_type' => 'queued',
            'occurred_at' => Carbon::now(),
            'payload' => [
                'type' => $type->value,
                'provider' => $this->provider->getName(),
            ],
        ]);

        // Dispatch Asynchronous Queue Job with resilient fallback
        try {
            SendEmailJob::dispatch($message->id);
        } catch (\Throwable $e) {
            Log::warning('EmailService: Queue dispatch failed, falling back to direct delivery', [
                'id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            try {
                $this->deliverQueuedMessage($message);
            } catch (\Throwable $deliveryEx) {
                Log::error('EmailService: Direct fallback delivery failed', [
                    'id' => $message->id,
                    'error' => $deliveryEx->getMessage(),
                ]);
            }
        }

        Log::info('EmailService: Outbound email processed', [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'to' => $toEmail,
            'type' => $type->value,
        ]);

        return $message;
    }

    /**
     * Deliver a queued email message via the configured EmailProviderInterface.
     */
    public function deliverQueuedMessage(EmailMessage $message): bool
    {
        $message->update(['status' => EmailMessageStatus::Sending]);

        $payload = [
            'to' => $message->to_email,
            'to_name' => $message->to_name,
            'from_email' => $message->from_email,
            'from_name' => $message->from_name,
            'reply_to' => $message->reply_to_email,
            'subject' => $message->subject,
            'html' => $message->body_html,
            'plain' => $message->body_plain,
            'metadata' => $message->metadata ?? [],
        ];

        $result = $this->provider->send($payload);

        if ($result['success']) {
            $message->update([
                'status' => EmailMessageStatus::Sent,
                'provider_message_id' => $result['message_id'],
                'provider_response' => $result['response'],
                'sent_at' => Carbon::now(),
                'error_message' => null,
            ]);

            EmailEvent::create([
                'email_message_id' => $message->id,
                'event_type' => 'send',
                'provider_event_id' => $result['message_id'],
                'payload' => $result['response'],
                'occurred_at' => Carbon::now(),
            ]);

            // Update Authoritative Contact activity
            if ($message->contact) {
                $message->contact->update([
                    'last_contact_at' => Carbon::now(),
                ]);
            }

            // Increment Account usage
            if ($message->account) {
                $message->account->increment('sent_today');
            }

            return true;
        }

        // Handle Delivery Error
        $message->update([
            'status' => EmailMessageStatus::Failed,
            'error_message' => $result['error'] ?? 'Unknown provider error',
            'provider_response' => $result['response'],
        ]);

        EmailEvent::create([
            'email_message_id' => $message->id,
            'event_type' => 'failure',
            'payload' => [
                'error' => $result['error'],
                'response' => $result['response'],
            ],
            'occurred_at' => Carbon::now(),
        ]);

        throw new RuntimeException("Email delivery failed for [{$message->uuid}]: ".($result['error'] ?? 'Unknown error'));
    }

    /**
     * Send a test email.
     */
    public function sendTestEmail(
        string $recipientEmail,
        ?string $subject = null,
        ?string $bodyHtml = null,
        ?User $sender = null
    ): EmailMessage {
        $subject = $subject ?? ('[TEST EMAIL] Bamcom AI CRM SES Verification - '.now()->toFormattedDateString());
        $bodyHtml = $bodyHtml ?? '
            <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">
                <h2 style="color: #0f172a;">Bamcom AI CRM - SES Test Verification</h2>
                <p style="color: #475569; font-size: 15px;">This is an automated test email confirming that your Amazon SES email infrastructure and queue pipeline are properly configured and operational.</p>
                <div style="background-color: #f1f5f9; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; color: #334155; margin: 16px 0;">
                    Status: Verified & Operational<br>
                    Timestamp: '.now()->toIso8601String().'<br>
                    Environment: '.app()->environment().'
                </div>
                <p style="color: #64748b; font-size: 12px;">Dispatched by Bamcom AI CRM Engine.</p>
            </div>
        ';

        return $this->send([
            'to_email' => $recipientEmail,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'type' => EmailMessageType::Test,
        ], $sender);
    }

    /**
     * Get or initialize the default EmailAccount.
     */
    public function getDefaultAccount(?User $user = null): EmailAccount
    {
        $account = EmailAccount::where('is_default', true)->where('is_active', true)->first();

        if ($account) {
            return $account;
        }

        $account = EmailAccount::where('is_active', true)->first();
        if ($account) {
            return $account;
        }

        // Initialize default SES account from environment settings
        return EmailAccount::create([
            'name' => 'Default Amazon SES Account',
            'provider' => 'ses',
            'from_name' => config('services.ses.from_name', env('AWS_SES_FROM_NAME', 'Bamcom AI CRM')),
            'from_email' => config('services.ses.from_address', env('AWS_SES_FROM_ADDRESS', 'noreply@bamcomcrm.com')),
            'reply_to_email' => config('services.ses.from_address', env('AWS_SES_FROM_ADDRESS', 'noreply@bamcomcrm.com')),
            'is_default' => true,
            'is_active' => true,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Aggregate email statistics and delivery health.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $totalSent = EmailMessage::whereIn('status', [EmailMessageStatus::Sent, EmailMessageStatus::Delivered])->count();
        $totalDelivered = EmailMessage::where('status', EmailMessageStatus::Delivered)->count();
        $totalBounced = EmailMessage::where('status', EmailMessageStatus::Bounced)->count();
        $totalComplained = EmailMessage::where('status', EmailMessageStatus::Complained)->count();
        $totalQueued = EmailMessage::where('status', EmailMessageStatus::Queued)->count();
        $totalFailed = EmailMessage::where('status', EmailMessageStatus::Failed)->count();

        $denominator = max(1, $totalSent + $totalFailed);
        $deliverabilityRate = round(($totalSent / $denominator) * 100, 2);
        $bounceRate = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 2) : 0.0;

        return [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_bounced' => $totalBounced,
            'total_complained' => $totalComplained,
            'total_queued' => $totalQueued,
            'total_failed' => $totalFailed,
            'deliverability_rate' => $deliverabilityRate,
            'bounce_rate' => $bounceRate,
            'active_suppressions' => DB::table('email_suppressions')->count(),
        ];
    }
}
