<?php

namespace Tests\Feature\Email;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Enums\EmailMarketingStatus;
use App\Enums\EmailMessageType;
use App\Enums\EmailSuppressionReason;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailSuppression;
use App\Models\SmartList;
use App\Models\User;
use App\Services\Email\EmailCampaignService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailCampaignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularUser;

    protected EmailCampaignService $campaignService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
        ]);
        $this->superAdmin->givePermissionTo(PermissionEnum::values());

        $this->regularUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => UserStatus::Active,
        ]);

        $this->campaignService = app(EmailCampaignService::class);
    }

    /**
     * Test authorized user can view campaign index and summary statistics.
     */
    public function test_user_with_permission_can_view_campaign_index_and_stats(): void
    {
        EmailCampaign::factory()->create([
            'name' => 'Investor Gala 2026',
            'status' => EmailCampaignStatus::Draft,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('email-campaigns.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'campaigns' => ['data'],
            'stats' => ['total_campaigns', 'total_sent', 'total_opened', 'total_clicked', 'active_count'],
            'statuses',
        ]);

        $this->assertEquals(1, $response->json('stats.total_campaigns'));
    }

    /**
     * Test unprivileged user is forbidden from campaign actions.
     */
    public function test_user_without_permission_cannot_access_campaigns(): void
    {
        $campaign = EmailCampaign::factory()->create();

        $this->actingAs($this->regularUser)
            ->get(route('email-campaigns.index'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->get(route('email-campaigns.show', $campaign))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->postJson(route('email-campaigns.store'), [
                'name' => 'Unauthorized Campaign',
                'subject' => 'Exclusive Deals',
                'body_html' => '<p>Offers</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
            ])
            ->assertForbidden();
    }

    /**
     * Test campaign requires an unsubscribe link for CAN-SPAM compliance.
     */
    public function test_campaign_enforces_can_spam_unsubscribe_link(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.store'), [
                'name' => 'Non-compliant Campaign',
                'subject' => 'Huge discounts on Lekki land!',
                'body_html' => '<p>Buy today for 20% off!</p>', // Missing unsubscribe
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['body_html']);

        // With unsubscribe tag
        $validResponse = $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.store'), [
                'name' => 'Compliant Campaign',
                'subject' => 'Huge discounts on Lekki land!',
                'body_html' => '<p>Buy today for 20% off!</p><p><a href="{{ unsubscribe_url }}">Unsubscribe</a></p>',
            ]);

        $validResponse->assertCreated();
        $this->assertDatabaseHas('email_campaigns', [
            'name' => 'Compliant Campaign',
            'status' => EmailCampaignStatus::Draft->value,
        ]);
    }

    /**
     * Test creating campaign with Smart List resolves recipients.
     */
    public function test_can_create_campaign_with_smart_list_and_resolve_recipients(): void
    {
        $contact1 = Contact::factory()->create([
            'email' => 'investor1@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Lekki Phase 1',
        ]);

        $contact2 = Contact::factory()->create([
            'email' => 'investor2@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Lekki Phase 1',
        ]);

        $smartList = SmartList::create([
            'name' => 'Lekki Investors',
            'slug' => 'lekki-investors',
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'contains', 'value' => 'Lekki'],
                ],
            ],
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.store'), [
                'name' => 'Lekki Duplex Showcase',
                'subject' => 'Exclusive Showcase for {{ contact.first_name }}',
                'smart_list_id' => $smartList->id,
                'body_html' => '<p>Check out our new estate.</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
            ]);

        $response->assertCreated();
        $campaignId = $response->json('campaign.id');

        $this->assertDatabaseHas('email_campaigns', [
            'id' => $campaignId,
            'smart_list_id' => $smartList->id,
            'eligible_recipients' => 2,
        ]);

        $this->assertDatabaseHas('email_campaign_recipients', [
            'email_campaign_id' => $campaignId,
            'contact_id' => $contact1->id,
            'status' => EmailCampaignRecipientStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('email_campaign_recipients', [
            'email_campaign_id' => $campaignId,
            'contact_id' => $contact2->id,
            'status' => EmailCampaignRecipientStatus::Pending->value,
        ]);
    }

    /**
     * Test audience preview checks compliance: suppression, consent, bounces, complaints.
     */
    public function test_audience_preview_verifies_eligibility_and_filters_ineligible_contacts(): void
    {
        $smartList = SmartList::create([
            'name' => 'Compliance Verification Audience',
            'slug' => 'compliance-verification-audience',
            'rule_groups' => [
                'logical_operator' => 'AND',
                'rules' => [
                    ['field' => 'contact.location', 'operator' => 'equals', 'value' => 'Compliance-Audit-Zone'],
                ],
            ],
        ]);

        // 1. Eligible Contact
        Contact::factory()->create([
            'email' => 'valid-eligible@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);

        // 2. Unsubscribed Contact
        Contact::factory()->create([
            'email' => 'unsubscribed-contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Unsubscribed,
            'has_opted_out' => true,
            'location' => 'Compliance-Audit-Zone',
        ]);

        // 3. Ledger Suppressed
        $suppressed = Contact::factory()->create([
            'email' => 'suppressed-contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);
        EmailSuppression::create([
            'email' => 'suppressed-contact@example.com',
            'reason' => EmailSuppressionReason::Manual,
            'contact_id' => $suppressed->id,
            'suppressed_at' => now(),
        ]);

        // 4. Hard Bounced
        Contact::factory()->create([
            'email' => 'bounced-contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Bounced,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);

        // 5. Spam Complaint
        $complained = Contact::factory()->create([
            'email' => 'complained-contact@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);
        EmailSuppression::create([
            'email' => 'complained-contact@example.com',
            'reason' => EmailSuppressionReason::Complaint,
            'contact_id' => $complained->id,
            'suppressed_at' => now(),
        ]);

        // 6. Pending Consent (not yet subscribed)
        Contact::factory()->create([
            'email' => 'pending-consent@example.com',
            'email_marketing_status' => EmailMarketingStatus::PendingConsent,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);

        // 7. Invalid Email
        Contact::factory()->create([
            'email' => 'not-a-valid-email',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'location' => 'Compliance-Audit-Zone',
        ]);

        $preview = $this->campaignService->previewAudience($smartList->id);

        $this->assertEquals(7, $preview['total_matching']);
        $this->assertEquals(1, $preview['eligible_count']);
        $this->assertEquals(6, $preview['skipped_count']);

        $this->assertEquals(1, $preview['skipped_breakdown']['unsubscribed']);
        $this->assertEquals(1, $preview['skipped_breakdown']['suppressed']);
        $this->assertEquals(1, $preview['skipped_breakdown']['hard_bounced']);
        $this->assertEquals(1, $preview['skipped_breakdown']['complained']);
        $this->assertEquals(1, $preview['skipped_breakdown']['not_subscribed']);
        $this->assertEquals(1, $preview['skipped_breakdown']['invalid_email']);
    }

    /**
     * Test dispatching a campaign queues discrete batch jobs without blocking HTTP request.
     */
    public function test_campaign_dispatch_divides_recipients_into_queued_batches(): void
    {
        Queue::fake();

        $campaign = EmailCampaign::factory()->create([
            'batch_size' => 2,
            'status' => EmailCampaignStatus::Draft,
            'body_html' => '<p>Offers</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $contact = Contact::factory()->create([
                'email' => "batch-tester{$i}@example.com",
                'email_marketing_status' => EmailMarketingStatus::Subscribed,
                'has_opted_out' => false,
            ]);

            EmailCampaignRecipient::factory()->create([
                'email_campaign_id' => $campaign->id,
                'contact_id' => $contact->id,
                'email' => $contact->email,
                'status' => EmailCampaignRecipientStatus::Pending,
                'batch_number' => ceil($i / 2),
            ]);
        }

        $campaign->update([
            'total_recipients' => 5,
            'eligible_recipients' => 5,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.send-now', $campaign));

        $response->assertOk();

        $this->assertEquals(EmailCampaignStatus::Sending, $campaign->fresh()->status);

        // 5 recipients / batch size 2 = 3 batches
        Queue::assertPushed(SendCampaignBatchJob::class, 3);
    }

    /**
     * Test SendCampaignBatchJob sends messages, links pivot, and marks campaign completed.
     */
    public function test_batch_job_sends_emails_and_completes_campaign(): void
    {
        $campaign = EmailCampaign::factory()->create([
            'status' => EmailCampaignStatus::Sending,
            'eligible_recipients' => 1,
            'body_html' => '<h3>Welcome {{ contact.first_name }}</h3><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
        ]);

        $contact = Contact::factory()->create([
            'email' => 'subscriber@example.com',
            'first_name' => 'Tunde',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
        ]);

        $recipient = EmailCampaignRecipient::factory()->create([
            'email_campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'email' => $contact->email,
            'status' => EmailCampaignRecipientStatus::Pending,
            'batch_number' => 1,
        ]);

        // Run the job synchronously
        $job = new SendCampaignBatchJob($campaign->id, [$recipient->id], 1);
        app()->call([$job, 'handle']);

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals(EmailCampaignRecipientStatus::Sent, $recipient->status);
        $this->assertNotNull($recipient->email_message_id);
        $this->assertNotNull($recipient->sent_at);

        $this->assertDatabaseHas('email_campaign_messages', [
            'email_campaign_id' => $campaign->id,
            'email_campaign_recipient_id' => $recipient->id,
            'email_message_id' => $recipient->email_message_id,
        ]);

        $this->assertEquals(1, $campaign->sent_count);
        $this->assertEquals(EmailCampaignStatus::Completed, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
    }

    /**
     * Test pause, resume, and cancel lifecycle transitions.
     */
    public function test_campaign_pause_resume_and_cancel_lifecycle(): void
    {
        Queue::fake();

        $campaign = EmailCampaign::factory()->create([
            'status' => EmailCampaignStatus::Sending,
            'started_at' => now(),
        ]);

        $recipient = EmailCampaignRecipient::factory()->create([
            'email_campaign_id' => $campaign->id,
            'status' => EmailCampaignRecipientStatus::Pending,
        ]);

        // 1. Pause
        $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.pause', $campaign))
            ->assertOk();

        $this->assertEquals(EmailCampaignStatus::Paused, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->paused_at);

        // 2. Resume
        $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.resume', $campaign))
            ->assertOk();

        $this->assertEquals(EmailCampaignStatus::Sending, $campaign->fresh()->status);

        // 3. Cancel
        $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.cancel', $campaign), ['reason' => 'Client budget changed'])
            ->assertOk();

        $fresh = $campaign->fresh();
        $this->assertEquals(EmailCampaignStatus::Cancelled, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals('Client budget changed', $fresh->cancellation_reason);

        // Remaining pending recipients should be marked skipped with campaign_cancelled
        $this->assertEquals(EmailCampaignRecipientStatus::Skipped, $recipient->fresh()->status);
        $this->assertEquals('campaign_cancelled', $recipient->fresh()->skip_reason);
    }

    /**
     * Test scheduling a campaign and automated execution via Artisan command.
     */
    public function test_schedule_campaign_and_command_execution(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'email' => 'future-buyer@example.com',
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
        ]);

        $campaign = EmailCampaign::factory()->create([
            'status' => EmailCampaignStatus::Draft,
            'body_html' => '<p>Future update</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
        ]);

        // Schedule for the future
        $this->campaignService->scheduleCampaign($campaign, now()->addHour());

        $this->assertEquals(EmailCampaignStatus::Scheduled, $campaign->fresh()->status);

        // Command should not dispatch future campaigns
        $this->artisan('email:send-scheduled-campaigns')->assertSuccessful();
        Queue::assertNothingPushed();

        // Move schedule time to the past
        $campaign->update(['scheduled_at' => now()->subMinute()]);

        // Command should now dispatch the campaign
        $this->artisan('email:send-scheduled-campaigns')->assertSuccessful();
        $this->assertEquals(EmailCampaignStatus::Sending, $campaign->fresh()->status);
        Queue::assertPushed(SendCampaignBatchJob::class);
    }

    /**
     * Test test send endpoint dispatches preview message without modifying campaign recipients.
     */
    public function test_send_test_email_dispatches_preview_without_altering_recipients(): void
    {
        $campaign = EmailCampaign::factory()->create([
            'name' => 'Gala Preview',
            'subject' => 'VIP Invitation',
            'body_html' => '<p>Exclusive Gala invitation for {{ contact.first_name }}.</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
        ]);

        $recipientEmail = 'developer-qa@bamcomcrm.com';

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('email-campaigns.send-test', $campaign), [
                'recipient_email' => $recipientEmail,
            ]);

        $response->assertAccepted();
        $response->assertJsonPath('email.to_email', $recipientEmail);
        $response->assertJsonPath('email.type', EmailMessageType::Test->value);

        // Assert no campaign recipients were modified
        $this->assertEquals(0, $campaign->recipients()->count());
    }

    /**
     * Test live progress endpoint returns real-time metrics.
     */
    public function test_progress_endpoint_returns_live_metrics(): void
    {
        $campaign = EmailCampaign::factory()->create([
            'status' => EmailCampaignStatus::Sending,
            'total_recipients' => 100,
            'eligible_recipients' => 80,
            'sent_count' => 40,
            'failed_count' => 2,
            'opened_count' => 10,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson(route('email-campaigns.progress', $campaign));

        $response->assertOk();
        $response->assertJsonPath('status', 'sending');
        $response->assertJsonPath('sent_count', 40);
        $response->assertJsonPath('eligible_recipients', 80);
        // (40 + 2) / 80 = 53%
        $this->assertEquals(53, $response->json('progress_percentage'));
    }
}
