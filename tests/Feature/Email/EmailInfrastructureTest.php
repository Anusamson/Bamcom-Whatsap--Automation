<?php

namespace Tests\Feature\Email;

use App\Enums\EmailMarketingStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use App\Enums\EmailSuppressionReason;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendEmailJob;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Models\User;
use App\Services\Email\EmailTemplateService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
        $this->superAdmin->givePermissionTo(PermissionEnum::values());
    }

    /**
     * Test transactional email is persisted with queued status and dispatched to queue.
     */
    public function test_transactional_email_is_persisted_queued_and_job_dispatched(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'email' => 'buyer@example.com',
            'first_name' => 'Adewale',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('emails.store'), [
                'to_email' => 'buyer@example.com',
                'subject' => 'Your Purchase Receipt #1042',
                'body_html' => '<h1>Thank you for your investment</h1><p>Your deposit has been acknowledged.</p>',
                'type' => 'transactional',
                'contact_id' => $contact->id,
            ]);

        $response->assertAccepted();
        $response->assertJsonPath('email.status', EmailMessageStatus::Queued->value);
        $response->assertJsonPath('email.to_email', 'buyer@example.com');

        $messageId = $response->json('email.id');

        $this->assertDatabaseHas('email_messages', [
            'id' => $messageId,
            'to_email' => 'buyer@example.com',
            'contact_id' => $contact->id,
            'status' => EmailMessageStatus::Queued->value,
            'type' => EmailMessageType::Transactional->value,
        ]);

        $this->assertDatabaseHas('email_events', [
            'email_message_id' => $messageId,
            'event_type' => 'queued',
        ]);

        Queue::assertPushed(SendEmailJob::class, function ($job) use ($messageId) {
            return $job->emailMessageId === $messageId;
        });
    }

    /**
     * Test SendEmailJob delivers the message and updates status to sent.
     */
    public function test_send_email_job_executes_delivery_and_records_sent_status(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'investor@example.com',
            'last_contact_at' => null,
        ]);

        $message = EmailMessage::factory()->create([
            'contact_id' => $contact->id,
            'to_email' => 'investor@example.com',
            'status' => EmailMessageStatus::Queued,
            'body_html' => '<p>Investment prospectus update</p>',
            'body_plain' => 'Investment prospectus update',
        ]);

        // Execute queued job synchronously
        $job = new SendEmailJob($message->id);
        app()->call([$job, 'handle']);

        $message->refresh();
        $contact->refresh();

        $this->assertEquals(EmailMessageStatus::Sent, $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertNotNull($message->provider_message_id);
        $this->assertNotNull($contact->last_contact_at);

        $this->assertDatabaseHas('email_events', [
            'email_message_id' => $message->id,
            'event_type' => 'send',
        ]);
    }

    /**
     * Test marketing email is blocked when recipient email is on suppression list.
     */
    public function test_marketing_email_blocked_if_recipient_is_on_suppression_list(): void
    {
        Queue::fake();

        EmailSuppression::create([
            'email' => 'suppressed.user@example.com',
            'reason' => EmailSuppressionReason::Bounce,
            'suppressed_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('emails.store'), [
                'to_email' => 'suppressed.user@example.com',
                'subject' => 'Special Real Estate Offer',
                'body_html' => '<p>Check out our new property estate</p>',
                'type' => 'marketing',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('to_email');

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('email_messages', [
            'to_email' => 'suppressed.user@example.com',
        ]);
    }

    /**
     * Test marketing email is blocked if contact has not consented or unsubscribed.
     */
    public function test_marketing_email_blocked_if_contact_has_not_opted_in(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'email' => 'unsubscribed.contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Unsubscribed,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('emails.store'), [
                'to_email' => 'unsubscribed.contact@example.com',
                'subject' => 'Weekly Properties',
                'body_html' => '<p>Offers</p>',
                'type' => 'marketing',
                'contact_id' => $contact->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('to_email');

        Queue::assertNothingPushed();
    }

    /**
     * Test transactional emails bypass marketing unsubscribed status.
     */
    public function test_transactional_email_bypasses_marketing_unsubscribed_status(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'email' => 'optedout.contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Unsubscribed,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('emails.store'), [
                'to_email' => 'optedout.contact@example.com',
                'subject' => 'Your Security Code',
                'body_html' => '<p>Code: 492042</p>',
                'type' => 'transactional',
                'contact_id' => $contact->id,
            ]);

        $response->assertAccepted();
        Queue::assertPushed(SendEmailJob::class);
    }

    /**
     * Test email template variable merge tags and plain text fallback conversion.
     */
    public function test_email_template_variable_interpolation_and_plaintext_generation(): void
    {
        $templateService = app(EmailTemplateService::class);

        $contact = Contact::factory()->create([
            'first_name' => 'Babajide',
            'last_name' => 'Sanwo',
            'email' => 'babajide@example.com',
        ]);

        $html = '<h2>Welcome {{contact.first_name}} {{contact.last_name}}</h2><p>Thank you for choosing {{company.name}}.</p><a href="{{unsubscribe_url}}">Opt out</a>';

        $variables = $templateService->buildVariablesForContact($contact);
        $rendered = $templateService->render($html, null, $variables);

        $this->assertStringContainsString('Welcome Babajide Sanwo', $rendered['html']);
        $this->assertStringContainsString('Welcome Babajide Sanwo', $rendered['plain']);
        $this->assertStringNotContainsString('<h2>', $rendered['plain']);
        $this->assertStringContainsString('Opt out (', $rendered['plain']);
    }

    /**
     * Test send test email endpoint works.
     */
    public function test_send_test_email_endpoint(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('emails.test'), [
                'to_email' => 'qa.tester@bamcomcrm.com',
                'subject' => 'Smoke Test Verification',
            ]);

        $response->assertAccepted();
        $response->assertJsonPath('email.type', EmailMessageType::Test->value);
        $response->assertJsonPath('email.to_email', 'qa.tester@bamcomcrm.com');

        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'qa.tester@bamcomcrm.com',
            'type' => EmailMessageType::Test->value,
            'status' => EmailMessageStatus::Queued->value,
        ]);

        Queue::assertPushed(SendEmailJob::class);
    }

    /**
     * Test SES bounce webhook records bounce event, increments counter, and suppresses contact.
     */
    public function test_ses_webhook_handles_bounce_event_and_suppresses_contact(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'bouncing.client@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'email_bounce_count' => 0,
        ]);

        $message = EmailMessage::factory()->create([
            'contact_id' => $contact->id,
            'to_email' => 'bouncing.client@example.com',
            'provider_message_id' => 'ses-unique-msg-999',
            'status' => EmailMessageStatus::Sent,
        ]);

        $webhookPayload = [
            'eventType' => 'Bounce',
            'mail' => [
                'messageId' => 'ses-unique-msg-999',
                'destination' => ['bouncing.client@example.com'],
                'timestamp' => now()->toIso8601String(),
            ],
            'bounce' => [
                'bounceType' => 'Permanent',
                'bounceSubType' => 'General',
                'bouncedRecipients' => [
                    [
                        'emailAddress' => 'bouncing.client@example.com',
                        'diagnosticCode' => 'smtp; 550 5.1.1 user unknown',
                    ],
                ],
                'feedbackId' => 'feedback-bounce-001',
            ],
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $webhookPayload);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $message->refresh();
        $contact->refresh();

        $this->assertEquals(EmailMessageStatus::Bounced, $message->status);
        $this->assertEquals(EmailMarketingStatus::Bounced, $contact->email_marketing_status);
        $this->assertEquals(1, $contact->email_bounce_count);
        $this->assertNotNull($contact->email_bounced_at);

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'bouncing.client@example.com',
            'reason' => EmailSuppressionReason::Bounce->value,
        ]);

        $this->assertDatabaseHas('email_events', [
            'email_message_id' => $message->id,
            'event_type' => 'bounced',
        ]);
    }

    /**
     * Test SES complaint and delivery webhook events.
     */
    public function test_ses_webhook_handles_complaint_and_delivery_events(): void
    {
        $contact = Contact::factory()->create(['email' => 'complaint.user@example.com']);
        $message = EmailMessage::factory()->create([
            'contact_id' => $contact->id,
            'to_email' => 'complaint.user@example.com',
            'provider_message_id' => 'ses-complaint-msg-123',
            'status' => EmailMessageStatus::Sent,
        ]);

        // Post Complaint Webhook
        $complaintPayload = [
            'eventType' => 'Complaint',
            'mail' => [
                'messageId' => 'ses-complaint-msg-123',
                'destination' => ['complaint.user@example.com'],
            ],
            'complaint' => [
                'complaintFeedbackType' => 'abuse',
                'complainedRecipients' => [
                    ['emailAddress' => 'complaint.user@example.com'],
                ],
                'feedbackId' => 'feedback-comp-777',
            ],
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $complaintPayload);
        $response->assertOk();

        $message->refresh();
        $contact->refresh();

        $this->assertEquals(EmailMessageStatus::Complained, $message->status);
        $this->assertEquals(EmailMarketingStatus::Suppressed, $contact->email_marketing_status);
        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'complaint.user@example.com',
            'reason' => EmailSuppressionReason::Complaint->value,
        ]);

        // Post Delivery Webhook on another message
        $deliveryMessage = EmailMessage::factory()->create([
            'to_email' => 'delivered.recipient@example.com',
            'provider_message_id' => 'ses-delivered-msg-456',
            'status' => EmailMessageStatus::Sent,
        ]);

        $deliveryPayload = [
            'eventType' => 'Delivery',
            'mail' => [
                'messageId' => 'ses-delivered-msg-456',
                'destination' => ['delivered.recipient@example.com'],
            ],
            'delivery' => [
                'smtpResponse' => '250 2.1.5 Ok',
            ],
        ];

        $delResponse = $this->postJson(route('api.v1.emails.webhook'), $deliveryPayload);
        $delResponse->assertOk();

        $deliveryMessage->refresh();
        $this->assertEquals(EmailMessageStatus::Delivered, $deliveryMessage->status);
        $this->assertNotNull($deliveryMessage->delivered_at);
    }

    /**
     * Test public unsubscribe route updates contact and adds suppression.
     */
    public function test_public_unsubscribe_route_updates_contact_and_suppression(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'unsubscribe.me@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
        ]);

        $response = $this->postJson(route('emails.unsubscribe.process'), [
            'email' => 'unsubscribe.me@example.com',
            'reason' => 'Too many emails',
        ]);

        $response->assertOk();

        $contact->refresh();
        $this->assertEquals(EmailMarketingStatus::Unsubscribed, $contact->email_marketing_status);
        $this->assertTrue($contact->has_opted_out);
        $this->assertNotNull($contact->email_unsubscribed_at);

        $this->assertDatabaseHas('email_suppressions', [
            'email' => 'unsubscribe.me@example.com',
            'reason' => EmailSuppressionReason::Unsubscribe->value,
        ]);
    }

    /**
     * Test that AWS credentials are never exposed via API endpoints.
     */
    public function test_aws_credentials_are_never_exposed_via_apis(): void
    {
        $account = EmailAccount::factory()->create([
            'name' => 'SES Outbound System',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson(route('emails.stats'));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('AWS_SECRET_ACCESS_KEY', $content);
        $this->assertStringNotContainsString('AWS_ACCESS_KEY_ID', $content);
    }
}
