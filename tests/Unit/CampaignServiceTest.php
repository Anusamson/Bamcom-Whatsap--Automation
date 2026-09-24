<?php

namespace Tests\Unit;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\Campaign\CampaignService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignService $service;

    protected WhatsAppMessageService $mockWhatsAppService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWhatsAppService = Mockery::mock(WhatsAppMessageService::class);
        $this->app->instance(WhatsAppMessageService::class, $this->mockWhatsAppService);

        $this->service = app(CampaignService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Create audience and calculate cached count.
     */
    public function test_creates_audience_and_computes_cached_count(): void
    {
        Contact::factory()->count(3)->create([
            'location' => 'Lekki Phase 1',
            'has_opted_out' => false,
        ]);

        $audience = $this->service->createAudience([
            'name' => 'Lekki Residents',
            'description' => 'Targeting Lekki corridor buyers',
            'filters' => ['locations' => ['Lekki']],
        ], $this->user);

        $this->assertDatabaseHas('audiences', [
            'id' => $audience->id,
            'name' => 'Lekki Residents',
            'cached_count' => 3,
        ]);
    }

    /**
     * Create campaign in draft or scheduled status.
     */
    public function test_creates_campaign_in_draft_and_scheduled_status(): void
    {
        $audience = Audience::factory()->create();

        // Draft
        $draftCampaign = $this->service->createCampaign([
            'name' => 'Q4 Festive Promo',
            'audience_id' => $audience->id,
            'message_type' => 'custom_text',
            'message_content' => 'Special discount on plots!',
        ], $this->user);

        $this->assertEquals(CampaignStatus::Draft, $draftCampaign->status);

        // Scheduled
        $scheduledCampaign = $this->service->createCampaign([
            'name' => 'New Year Broadcast',
            'audience_id' => $audience->id,
            'message_type' => 'custom_text',
            'message_content' => 'Happy New Year!',
            'scheduled_at' => now()->addDays(5)->toDateTimeString(),
        ], $this->user);

        $this->assertEquals(CampaignStatus::Scheduled, $scheduledCampaign->status);
        $this->assertNotNull($scheduledCampaign->scheduled_at);
    }

    /**
     * Launch campaign resolves audience, creates chunked recipients, and dispatches batch 1.
     */
    public function test_launch_campaign_resolves_audience_creates_chunked_recipients_and_dispatches_first_batch(): void
    {
        Queue::fake();

        $contacts = Contact::factory()->count(5)->create([
            'has_opted_out' => false,
            'location' => 'Ikoyi',
        ]);

        $audience = Audience::factory()->create([
            'filters' => ['locations' => ['Ikoyi']],
        ]);

        $campaign = Campaign::factory()->create([
            'audience_id' => $audience->id,
            'batch_size' => 2,
            'status' => CampaignStatus::Draft,
        ]);

        $this->service->launchCampaign($campaign);

        $campaign->refresh();

        $this->assertEquals(CampaignStatus::Running, $campaign->status);
        $this->assertEquals(5, $campaign->total_recipients);
        $this->assertNotNull($campaign->started_at);

        // Check recipients creation and chunking
        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->orderBy('id')->get();
        $this->assertCount(5, $recipients);

        // First 2 should be in batch 1, next 2 in batch 2, last 1 in batch 3
        $this->assertEquals(1, $recipients[0]->batch_number);
        $this->assertEquals(1, $recipients[1]->batch_number);
        $this->assertEquals(2, $recipients[2]->batch_number);
        $this->assertEquals(2, $recipients[3]->batch_number);
        $this->assertEquals(3, $recipients[4]->batch_number);

        // Check first batch job dispatched
        Queue::assertPushed(SendCampaignBatchJob::class, function (SendCampaignBatchJob $job) use ($campaign) {
            return $job->campaignId === $campaign->id && $job->batchNumber === 1;
        });
    }

    /**
     * Opt-out defense: Opted out contact is skipped and marked opted_out before sending.
     */
    public function test_send_to_recipient_blocks_opted_out_contact(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Emeka',
            'phone' => '+2348012345678',
            'has_opted_out' => true,
        ]);

        $campaign = Campaign::factory()->running()->create();

        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        // Mock shouldn't receive any send calls
        $this->mockWhatsAppService->shouldReceive('sendTextMessage')->never();
        $this->mockWhatsAppService->shouldReceive('sendTemplateMessage')->never();

        $this->service->sendToRecipient($campaign, $recipient);

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals(CampaignRecipientStatus::OptedOut, $recipient->status);
        $this->assertEquals(1, $campaign->opted_out_count);
        $this->assertEquals(1, $campaign->processed_recipients);
        $this->assertEquals(0, $campaign->sent_count);
    }

    /**
     * Send custom text message interpolates merge tokens and sends.
     */
    public function test_send_to_recipient_interpolates_tokens_and_sends_text_message(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Folake',
            'last_name' => 'Bello',
            'phone' => '+2348033333333',
            'has_opted_out' => false,
        ]);

        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'score' => 85,
        ]);

        $campaign = Campaign::factory()->running()->create([
            'message_type' => 'custom_text',
            'message_content' => 'Hello {{contact.first_name}} {{contact.last_name}}, your lead score is {{lead.score}}!',
        ]);

        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'lead_id' => $lead->id,
            'phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        $expectedMessageId = 'wamid.HBgLMjM0ODAzMzMzMzMzA=';

        $this->mockWhatsAppService->shouldReceive('sendTextMessage')
            ->once()
            ->with(
                $contact->phone,
                'Hello Folake Bello, your lead score is 85!',
                Mockery::type(Contact::class)
            )
            ->andReturn([
                'success' => true,
                'message_id' => $expectedMessageId,
                'response' => ['status' => 'accepted'],
            ]);

        $this->service->sendToRecipient($campaign, $recipient);

        $recipient->refresh();
        $campaign->refresh();

        $this->assertEquals(CampaignRecipientStatus::Sent, $recipient->status);
        $this->assertEquals($expectedMessageId, $recipient->meta_message_id);
        $this->assertNotNull($recipient->sent_at);
        $this->assertEquals(1, $campaign->sent_count);

        $this->assertDatabaseHas('campaign_messages', [
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'body' => 'Hello Folake Bello, your lead score is 85!',
        ]);
    }

    /**
     * Send approved WhatsApp template message interpolates parameters.
     */
    public function test_send_to_recipient_interpolates_parameters_and_sends_template(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'name' => 'property_vip_launch',
            'language' => 'en_US',
            'body_text' => 'Hello {{1}}, welcome to {{2}}.',
        ]);

        $contact = Contact::factory()->create([
            'first_name' => 'Dayo',
            'phone' => '+2348044444444',
            'has_opted_out' => false,
        ]);

        $campaign = Campaign::factory()->running()->create([
            'message_type' => 'template',
            'whatsapp_template_id' => $template->id,
            'template_parameters' => ['{{contact.first_name}}', 'Silverstone Estates'],
        ]);

        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        $expectedMessageId = 'wamid.HBgLMjM0ODg0NDQ0NDQ0NA=';

        $this->mockWhatsAppService->shouldReceive('sendTemplateMessage')
            ->once()
            ->with(
                $contact->phone,
                $template->name,
                ['Dayo', 'Silverstone Estates'],
                'en_US',
                Mockery::type(Contact::class)
            )
            ->andReturn([
                'success' => true,
                'message_id' => $expectedMessageId,
                'response' => ['status' => 'accepted'],
            ]);

        $this->service->sendToRecipient($campaign, $recipient);

        $recipient->refresh();
        $this->assertEquals(CampaignRecipientStatus::Sent, $recipient->status);
    }

    /**
     * Process batch sends recipients and schedules next delayed batch.
     */
    public function test_process_batch_sends_recipients_and_queues_next_delayed_batch(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->running()->create([
            'batch_size' => 2,
            'batch_delay_seconds' => 15,
        ]);

        $contact1 = Contact::factory()->create(['phone' => '+2348011111111', 'has_opted_out' => false]);
        $contact2 = Contact::factory()->create(['phone' => '+2348022222222', 'has_opted_out' => false]);
        $contact3 = Contact::factory()->create(['phone' => '+2348033333333', 'has_opted_out' => false]);

        $r1 = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact1->id, 'batch_number' => 1]);
        $r2 = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact2->id, 'batch_number' => 1]);
        $r3 = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact3->id, 'batch_number' => 2]);

        $this->mockWhatsAppService->shouldReceive('sendTextMessage')
            ->twice()
            ->andReturn(['success' => true, 'message_id' => 'wamid.test']);

        $this->service->processBatch($campaign, 1);

        $r1->refresh();
        $r2->refresh();
        $this->assertEquals(CampaignRecipientStatus::Sent, $r1->status);
        $this->assertEquals(CampaignRecipientStatus::Sent, $r2->status);

        // Next batch (2) should be queued with 15s delay
        Queue::assertPushed(SendCampaignBatchJob::class, function (SendCampaignBatchJob $job) use ($campaign) {
            return $job->campaignId === $campaign->id && $job->batchNumber === 2 && $job->delay !== null;
        });
    }

    /**
     * Process batch completes campaign when all batches are done.
     */
    public function test_process_batch_completes_campaign_when_all_batches_finished(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->running()->create([
            'batch_size' => 2,
        ]);

        $contact = Contact::factory()->create(['phone' => '+2348011111111', 'has_opted_out' => false]);
        $r = CampaignRecipient::factory()->create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'batch_number' => 1]);

        $this->mockWhatsAppService->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn(['success' => true, 'message_id' => 'wamid.test']);

        $this->service->processBatch($campaign, 1);

        $campaign->refresh();

        $this->assertEquals(CampaignStatus::Completed, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
        Queue::assertNotPushed(SendCampaignBatchJob::class);
    }

    /**
     * Pause and resume campaign.
     */
    public function test_pause_and_resume_campaign(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->running()->create();
        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'batch_number' => 2,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        // Pause
        $this->service->pauseCampaign($campaign);
        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Paused, $campaign->status);
        $this->assertNotNull($campaign->paused_at);

        // Resume
        $this->service->resumeCampaign($campaign);
        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Running, $campaign->status);
        $this->assertNull($campaign->paused_at);

        Queue::assertPushed(SendCampaignBatchJob::class, function (SendCampaignBatchJob $job) use ($campaign) {
            return $job->campaignId === $campaign->id && $job->batchNumber === 2;
        });
    }

    /**
     * Cancel campaign marks pending recipients as skipped.
     */
    public function test_cancel_campaign_marks_pending_recipients_as_skipped(): void
    {
        $campaign = Campaign::factory()->running()->create();

        $pendingRecipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        $this->service->cancelCampaign($campaign, 'Budget exhausted');

        $campaign->refresh();
        $pendingRecipient->refresh();

        $this->assertEquals(CampaignStatus::Cancelled, $campaign->status);
        $this->assertEquals('Budget exhausted', $campaign->cancellation_reason);
        $this->assertNotNull($campaign->cancelled_at);

        $this->assertEquals(CampaignRecipientStatus::Skipped, $pendingRecipient->status);
        $this->assertStringContainsString('Budget exhausted', $pendingRecipient->error_message);
    }
}
