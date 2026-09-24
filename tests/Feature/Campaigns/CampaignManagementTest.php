<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\SendCampaignBatchJob;
use App\Models\Audience;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);
    }

    /**
     * User can view campaigns listing page.
     */
    public function test_user_can_view_campaigns_index_page(): void
    {
        Campaign::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('campaigns.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Campaigns/Index')
            ->has('campaigns.data', 3)
            ->has('metrics')
        );
    }

    /**
     * User can view campaign creation page.
     */
    public function test_user_can_view_campaign_create_page(): void
    {
        Audience::factory()->create();
        WhatsAppTemplate::factory()->create(['status' => 'APPROVED']);

        $response = $this->actingAs($this->user)->get(route('campaigns.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Campaigns/Create')
            ->has('audiences')
            ->has('templates')
        );
    }

    /**
     * User can create a new campaign.
     */
    public function test_user_can_create_campaign(): void
    {
        $audience = Audience::factory()->create();

        $payload = [
            'name' => 'Epe Waterfront Launch',
            'description' => 'Targeting high-budget commercial investors',
            'audience_id' => $audience->id,
            'message_type' => 'custom_text',
            'message_content' => 'Plots available now in Epe with C of O!',
            'batch_size' => 100,
            'batch_delay_seconds' => 10,
        ];

        $response = $this->actingAs($this->user)->post(route('campaigns.store'), $payload);

        $campaign = Campaign::where('name', 'Epe Waterfront Launch')->first();
        $this->assertNotNull($campaign);

        $response->assertRedirect(route('campaigns.show', $campaign));
        $this->assertEquals(CampaignStatus::Draft, $campaign->status);
        $this->assertEquals(100, $campaign->batch_size);
        $this->assertEquals(10, $campaign->batch_delay_seconds);
    }

    /**
     * User can view campaign show page with recipients and audit events.
     */
    public function test_user_can_view_campaign_show_page(): void
    {
        $campaign = Campaign::factory()->create();
        $contact = Contact::factory()->create();

        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('campaigns.show', $campaign));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Campaigns/Show')
            ->has('campaign')
            ->has('recipients.data', 1)
            ->has('events')
        );
    }

    /**
     * User can launch a campaign.
     */
    public function test_user_can_launch_campaign(): void
    {
        Queue::fake();

        $contact = Contact::factory()->create([
            'has_opted_out' => false,
            'location' => 'Lekki',
        ]);

        $audience = Audience::factory()->create([
            'filters' => ['locations' => ['Lekki']],
        ]);

        $campaign = Campaign::factory()->create([
            'audience_id' => $audience->id,
            'status' => CampaignStatus::Draft,
        ]);

        $response = $this->actingAs($this->user)->post(route('campaigns.launch', $campaign));

        $response->assertSessionHas('success');
        $campaign->refresh();

        $this->assertEquals(CampaignStatus::Running, $campaign->status);
        $this->assertEquals(1, $campaign->total_recipients);
        Queue::assertPushed(SendCampaignBatchJob::class);
    }

    /**
     * User can pause, resume, and cancel a campaign.
     */
    public function test_user_can_pause_resume_and_cancel_campaign(): void
    {
        Queue::fake();

        $campaign = Campaign::factory()->running()->create();
        $contact = Contact::factory()->create();
        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => CampaignRecipientStatus::Pending,
            'batch_number' => 1,
        ]);

        // Pause
        $this->actingAs($this->user)->post(route('campaigns.pause', $campaign));
        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Paused, $campaign->status);

        // Resume
        $this->actingAs($this->user)->post(route('campaigns.resume', $campaign));
        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Running, $campaign->status);

        // Cancel
        $this->actingAs($this->user)->post(route('campaigns.cancel', $campaign), [
            'reason' => 'User stopped broadcast',
        ]);
        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Cancelled, $campaign->status);
        $this->assertEquals('User stopped broadcast', $campaign->cancellation_reason);
    }

    /**
     * User can view audiences listing.
     */
    public function test_user_can_view_audiences_index_page(): void
    {
        Audience::factory()->count(2)->create();

        $response = $this->actingAs($this->user)->get(route('audiences.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Campaigns/Audiences')
            ->has('audiences.data', 2)
            ->has('pipelineStages')
            ->has('temperatures')
        );
    }

    /**
     * User can create and delete audience.
     */
    public function test_user_can_create_and_delete_audience(): void
    {
        Contact::factory()->count(2)->create([
            'has_opted_out' => false,
            'location' => 'Ajah',
        ]);

        $payload = [
            'name' => 'Ajah Buyers',
            'description' => 'Investors looking for land in Ajah',
            'filters' => [
                'locations' => ['Ajah'],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('audiences.store'), $payload);

        $response->assertSessionHas('success');

        $audience = Audience::where('name', 'Ajah Buyers')->first();
        $this->assertNotNull($audience);
        $this->assertEquals(2, $audience->cached_count);

        // Delete
        $deleteResponse = $this->actingAs($this->user)->delete(route('audiences.destroy', $audience));
        $deleteResponse->assertSessionHas('success');

        $this->assertSoftDeleted('audiences', ['id' => $audience->id]);
    }

    /**
     * Audience preview endpoint returns live matching count.
     */
    public function test_audience_preview_endpoint_returns_matching_count(): void
    {
        Contact::factory()->count(4)->create([
            'has_opted_out' => false,
            'location' => 'Victoria Island',
        ]);

        Contact::factory()->create([
            'has_opted_out' => true,
            'location' => 'Victoria Island',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('audiences.preview'), [
            'filters' => ['locations' => ['Victoria Island']],
        ]);

        $response->assertOk();
        $response->assertJsonPath('count', 4);
        $response->assertJsonPath('total_count', 4);
    }

    /**
     * Contact opt-out cancels pending campaign recipients.
     */
    public function test_contact_opt_out_cancels_pending_campaign_recipients(): void
    {
        $contact = Contact::factory()->create([
            'has_opted_out' => false,
        ]);

        $campaign = Campaign::factory()->running()->create();

        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => CampaignRecipientStatus::Pending,
        ]);

        // Contact opts out
        $contact->optOut('User sent STOP via WhatsApp');

        $this->assertTrue($contact->fresh()->has_opted_out);
        $this->assertEquals('User sent STOP via WhatsApp', $contact->fresh()->opt_out_reason);

        // Recipient must be cancelled to OptedOut
        $recipient->refresh();
        $this->assertEquals(CampaignRecipientStatus::OptedOut, $recipient->status);
        $this->assertStringContainsString('opted out', strtolower($recipient->error_message));
    }
}
