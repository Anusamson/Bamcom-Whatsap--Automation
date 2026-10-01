<?php

namespace Tests\Feature\Email;

use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailCampaignStatus;
use App\Enums\EmailMarketingStatus;
use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use App\Enums\EmailSuppressionReason;
use App\Enums\InspectionStatus;
use App\Enums\LeadStatus;
use App\Enums\PermissionEnum;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Models\FollowUpSequence;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\User;
use App\Services\Email\EmailAttributionService;
use App\Services\Email\EmailCampaignService;
use App\Services\Email\EmailService;
use App\Services\Email\EmailSuppressionService;
use App\Services\Sequence\SequenceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailEventsAndAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected EmailCampaign $campaign;

    protected EmailCampaignRecipient $recipient;

    protected EmailMessage $emailMessage;

    protected EmailSuppressionService $suppressionService;

    protected EmailAttributionService $attributionService;

    protected SequenceService $sequenceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->user = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'status' => UserStatus::Active,
            'name' => 'Sales Director Ade',
        ]);
        $this->user->givePermissionTo(PermissionEnum::values());

        $this->contact = Contact::factory()->create([
            'first_name' => 'Emeka',
            'last_name' => 'Nwosu',
            'email' => 'emeka.nwosu@example.com',
            'phone' => '+2348011223344',
            'status' => ContactStatus::Lead,
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'assigned_user_id' => $this->user->id,
        ]);

        $this->campaign = EmailCampaign::factory()->create([
            'name' => 'Banana Island Waterfront Launch',
            'status' => EmailCampaignStatus::Sending,
            'subject' => 'Exclusive Luxury Waterfront Villas',
            'body_html' => '<p>Dear {{contact.first_name}}, explore our latest estate. <a href="{{unsubscribe_url}}">Unsubscribe</a></p>',
            'total_recipients' => 10,
            'eligible_recipients' => 10,
            'sent_count' => 1,
            'delivered_count' => 0,
            'started_at' => now()->subDay(),
            'created_by' => $this->user->id,
        ]);

        $this->emailMessage = EmailMessage::create([
            'uuid' => (string) Str::uuid(),
            'to_email' => $this->contact->email,
            'to_name' => $this->contact->full_name,
            'from_email' => 'sales@bamcomcrm.com',
            'from_name' => 'Bamcom Real Estate',
            'subject' => 'Exclusive Luxury Waterfront Villas',
            'body_html' => '<p>Dear Emeka, explore our latest estate.</p>',
            'type' => EmailMessageType::Marketing->value,
            'status' => EmailMessageStatus::Sent,
            'contact_id' => $this->contact->id,
            'provider_message_id' => 'ses-msg-123456789',
            'sent_at' => now()->subDay(),
            'metadata' => [
                'campaign_id' => $this->campaign->id,
            ],
        ]);

        $this->recipient = EmailCampaignRecipient::create([
            'email_campaign_id' => $this->campaign->id,
            'contact_id' => $this->contact->id,
            'email' => $this->contact->email,
            'status' => EmailCampaignRecipientStatus::Sent,
            'email_message_id' => $this->emailMessage->id,
            'sent_at' => now()->subDay(),
        ]);

        $this->suppressionService = app(EmailSuppressionService::class);
        $this->attributionService = app(EmailAttributionService::class);
        $this->sequenceService = app(SequenceService::class);
    }

    /**
     * Test duplicate webhook events are recognized and ignored idempotently.
     */
    public function test_duplicate_events_prevent_redundant_processing(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
                'delivery' => [
                    'smtpResponse' => '250 2.0.0 OK 1729000123 smtp-delivery',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        // 1. First event processing succeeds
        $firstResponse = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $firstResponse->assertOk();
        $firstResponse->assertJson(['status' => 'success', 'event' => 'delivered']);

        $this->assertEquals(EmailMessageStatus::Delivered, $this->emailMessage->fresh()->status);
        $this->assertEquals(1, $this->campaign->fresh()->delivered_count);
        $this->assertEquals(1, EmailEvent::where('email_message_id', $this->emailMessage->id)->count());

        // 2. Duplicate event sent (SES retry simulation)
        $secondResponse = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $secondResponse->assertOk();
        $secondResponse->assertJson(['status' => 'duplicate']);

        // Assert no double counting
        $this->assertEquals(1, $this->campaign->fresh()->delivered_count);
        $this->assertEquals(1, EmailEvent::where('email_message_id', $this->emailMessage->id)->count());
    }

    /**
     * Test hard bounce event suppresses contact, updates recipient, and prevents future marketing.
     */
    public function test_hard_bounce_suppresses_contact_and_updates_campaign_recipient(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bounceSubType' => 'General',
                    'feedbackId' => 'bounce-feedback-uuid-999',
                    'bouncedRecipients' => [
                        [
                            'emailAddress' => $this->contact->email,
                            'diagnosticCode' => 'smtp; 550 5.1.1 User unknown',
                        ],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $response->assertOk();

        // 1. Message updated
        $messageFresh = $this->emailMessage->fresh();
        $this->assertEquals(EmailMessageStatus::Bounced, $messageFresh->status);
        $this->assertNotNull($messageFresh->bounced_at);

        // 2. Campaign recipient updated
        $recipientFresh = $this->recipient->fresh();
        $this->assertEquals(EmailCampaignRecipientStatus::Bounced, $recipientFresh->status);
        $this->assertNotNull($recipientFresh->bounced_at);
        $this->assertEquals(1, $this->campaign->fresh()->bounced_count);

        // 3. Contact updated and suppressed
        $contactFresh = $this->contact->fresh();
        $this->assertEquals(EmailMarketingStatus::Bounced, $contactFresh->email_marketing_status);
        $this->assertTrue($this->suppressionService->isSuppressed($this->contact->email));
        $this->assertFalse($this->suppressionService->canReceiveMarketing($this->contact->email));

        $this->assertDatabaseHas('email_suppressions', [
            'email' => $this->contact->email,
            'reason' => EmailSuppressionReason::Bounce->value,
        ]);

        // 4. CRM activity timeline recorded
        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'email_bounced',
        ]);
    }

    /**
     * Test soft bounce (transient) increments bounce count without immediate suppression.
     */
    public function test_soft_bounce_increments_bounce_count_without_immediate_suppression(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
                'bounce' => [
                    'bounceType' => 'Transient',
                    'bounceSubType' => 'MailboxFull',
                    'feedbackId' => 'bounce-feedback-soft-111',
                    'bouncedRecipients' => [
                        [
                            'emailAddress' => $this->contact->email,
                            'diagnosticCode' => 'smtp; 452 4.2.2 Mailbox full',
                        ],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $response->assertOk();

        // Contact bounce count incremented, but not yet suppressed
        $contactFresh = $this->contact->fresh();
        $this->assertEquals(1, $contactFresh->email_bounce_count);
        $this->assertFalse($this->suppressionService->isSuppressed($this->contact->email));
    }

    /**
     * Test complaint event immediately suppresses contact and halts future sends.
     */
    public function test_complaint_suppresses_contact_and_updates_campaign_recipient(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Complaint',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
                'complaint' => [
                    'complaintFeedbackType' => 'abuse',
                    'feedbackId' => 'complaint-feedback-uuid-888',
                    'complainedRecipients' => [
                        [
                            'emailAddress' => $this->contact->email,
                        ],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $response->assertOk();

        // Recipient updated
        $this->assertEquals(EmailCampaignRecipientStatus::Complained, $this->recipient->fresh()->status);
        $this->assertNotNull($this->recipient->fresh()->complained_at);
        $this->assertEquals(1, $this->campaign->fresh()->complained_count);

        // Suppression registered
        $this->assertTrue($this->suppressionService->isSuppressed($this->contact->email));
        $this->assertEquals(EmailMarketingStatus::Suppressed, $this->contact->fresh()->email_marketing_status);

        // Activity timeline
        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'email_complaint',
        ]);
    }

    /**
     * Test unsubscribe event updates marketing status, suppresses contact, and updates campaign.
     */
    public function test_unsubscribe_event_suppresses_contact_and_updates_campaign(): void
    {
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Unsubscribe',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
            ]),
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $response->assertOk();

        // Contact updated
        $contactFresh = $this->contact->fresh();
        $this->assertEquals(EmailMarketingStatus::Unsubscribed, $contactFresh->email_marketing_status);
        $this->assertTrue((bool) $contactFresh->has_opted_out);
        $this->assertNotNull($contactFresh->email_unsubscribed_at);

        // Campaign recipient updated
        $this->assertEquals(EmailCampaignRecipientStatus::Unsubscribed, $this->recipient->fresh()->status);
        $this->assertNotNull($this->recipient->fresh()->unsubscribed_at);
        $this->assertEquals(1, $this->campaign->fresh()->unsubscribed_count);

        // Activity timeline
        $this->assertDatabaseHas('activities', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'email_unsubscribed',
        ]);
    }

    /**
     * Test future marketing sends are blocked when contact is suppressed.
     */
    public function test_suppression_blocks_future_marketing_sends(): void
    {
        // Suppress contact email
        $this->suppressionService->suppress($this->contact->email, EmailSuppressionReason::Complaint);

        $emailService = app(EmailService::class);
        $campaignService = app(EmailCampaignService::class);

        // 1. Direct marketing send should fail with validation exception
        $this->expectException(ValidationException::class);
        $emailService->send([
            'to_email' => $this->contact->email,
            'subject' => 'VIP Property Pitch',
            'body_html' => '<p>Pitch</p>',
            'type' => EmailMessageType::Marketing,
            'contact_id' => $this->contact->id,
        ]);

        // 2. Campaign eligibility verification should mark contact as ineligible
        $eligibility = $campaignService->verifyContactEligibility($this->contact->fresh());
        $this->assertFalse($eligibility['eligible']);
        $this->assertStringContainsString('suppression', strtolower($eligibility['reason']));
    }

    /**
     * Test hard bounce, complaint, and unsubscribe automatically cancel active sequences.
     */
    public function test_hard_bounce_and_complaint_cancel_active_sequence_enrollments(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
            'current_step_number' => 0,
            'enrolled_at' => now(),
        ]);

        // Trigger hard bounce webhook
        $payload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail' => [
                    'messageId' => 'ses-msg-123456789',
                    'destination' => [$this->contact->email],
                    'timestamp' => now()->toIso8601String(),
                ],
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'feedbackId' => 'bounce-cancel-seq-99',
                    'bouncedRecipients' => [
                        ['emailAddress' => $this->contact->email, 'diagnosticCode' => '550 User not found'],
                    ],
                ],
            ]),
        ];

        $response = $this->postJson(route('api.v1.emails.webhook'), $payload);
        $response->assertOk();

        // Enrollment should be automatically cancelled
        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollmentFresh->status);
        $this->assertStringContainsString('bounce', strtolower((string) $enrollmentFresh->cancellation_reason));
    }

    /**
     * Test delivered, opened, and clicked events update recipient and CRM activity timeline.
     */
    public function test_delivered_opened_clicked_events_update_recipient_and_timeline(): void
    {
        // 1. Delivery
        $this->postJson(route('api.v1.emails.webhook'), [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Delivery',
                'mail' => ['messageId' => 'ses-msg-123456789'],
                'delivery' => ['smtpResponse' => '250 OK'],
            ]),
        ])->assertOk();

        $this->assertEquals(EmailCampaignRecipientStatus::Delivered, $this->recipient->fresh()->status);
        $this->assertDatabaseHas('activities', ['contact_id' => $this->contact->id, 'activity_type' => 'email_delivered']);

        // 2. Open
        $this->postJson(route('api.v1.emails.webhook'), [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Open',
                'mail' => ['messageId' => 'ses-msg-123456789'],
                'open' => ['ipAddress' => '197.210.54.12', 'userAgent' => 'Mozilla/5.0 Safari/605.1.15'],
            ]),
        ])->assertOk();

        $this->assertEquals(EmailCampaignRecipientStatus::Opened, $this->recipient->fresh()->status);
        $this->assertDatabaseHas('activities', ['contact_id' => $this->contact->id, 'activity_type' => 'email_opened']);

        // 3. Click
        $this->postJson(route('api.v1.emails.webhook'), [
            'Type' => 'Notification',
            'Message' => json_encode([
                'eventType' => 'Click',
                'mail' => ['messageId' => 'ses-msg-123456789'],
                'click' => ['link' => 'https://bamcomcrm.com/properties/eko-atlantic-penthouse'],
            ]),
        ])->assertOk();

        $this->assertEquals(EmailCampaignRecipientStatus::Clicked, $this->recipient->fresh()->status);
        $this->assertDatabaseHas('activities', ['contact_id' => $this->contact->id, 'activity_type' => 'email_clicked']);
        $this->assertDatabaseHas('email_events', [
            'email_message_id' => $this->emailMessage->id,
            'event_type' => 'clicked',
            'link_url' => 'https://bamcomcrm.com/properties/eko-atlantic-penthouse',
        ]);
    }

    /**
     * Test campaign analytics rates calculation derived strictly from stored data.
     */
    public function test_campaign_analytics_rates_calculation(): void
    {
        $campaign = EmailCampaign::factory()->create([
            'total_recipients' => 100,
            'eligible_recipients' => 100,
            'sent_count' => 100,
            'delivered_count' => 80,
            'opened_count' => 40,
            'clicked_count' => 20,
            'bounced_count' => 5,
            'unsubscribed_count' => 2,
        ]);

        $this->assertEquals(80.0, $campaign->deliveryRate()); // 80 / 100 = 80%
        $this->assertEquals(50.0, $campaign->openRate()); // 40 / 80 = 50%
        $this->assertEquals(25.0, $campaign->clickRate()); // 20 / 80 = 25%
        $this->assertEquals(50.0, $campaign->clickToOpenRate()); // 20 / 40 = 50%
        $this->assertEquals(5.0, $campaign->bounceRate()); // 5 / 100 = 5%
        $this->assertEquals(2.5, $campaign->unsubscribeRate()); // 2 / 80 = 2.5%

        $summary = $campaign->getAnalyticsSummary();
        $this->assertEquals(80.0, $summary['delivery_rate']);
        $this->assertEquals(50.0, $summary['open_rate']);
        $this->assertEquals(25.0, $summary['click_rate']);
        $this->assertEquals(50.0, $summary['click_to_open_rate']);
        $this->assertEquals(5.0, $summary['bounce_rate']);
        $this->assertEquals(2.5, $summary['unsubscribe_rate']);
    }

    /**
     * Test sales attribution: campaign -> contact -> lead -> inspection -> opportunity -> won deal -> revenue.
     */
    public function test_sales_attribution_funnel_and_attributed_revenue(): void
    {
        // 1. Create Lead for targeted contact
        $lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->user->id,
            'title' => 'Eko Atlantic 4-Bedroom Inquiry',
            'status' => LeadStatus::ProposalSent,
            'score' => 85,
            'created_at' => now(),
        ]);

        // 2. Create Site Inspection
        $inspection = Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $lead->id,
            'status' => InspectionStatus::Completed,
            'estate_name' => 'Eko Atlantic City',
            'created_at' => now(),
        ]);

        // 3. Create Won Deal (Sale)
        $wonDeal = Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $lead->id,
            'title' => 'Eko Atlantic Penthouse Sale',
            'deal_value' => 75000000.00,
            'status' => DealStatus::Won,
            'actual_close_date' => now(),
            'created_at' => now(),
        ]);

        // Calculate sales attribution via service
        $attribution = $this->attributionService->getCampaignAttribution($this->campaign, 90);

        $this->assertEquals(1, $attribution['attribution']['campaign_generated_leads']);
        $this->assertEquals(1, $attribution['attribution']['campaign_generated_inspections']);
        $this->assertEquals(1, $attribution['attribution']['campaign_generated_opportunities']);
        $this->assertEquals(1, $attribution['attribution']['campaign_generated_sales']);
        $this->assertEquals(75000000.00, $attribution['attribution']['attributed_revenue']);
        $this->assertEquals('₦75,000,000.00', $attribution['attribution']['formatted_attributed_revenue']);
        $this->assertEquals(7500000.00, $attribution['attribution']['revenue_per_recipient']); // 75,000,000 / 10 recipients

        $this->assertCount(8, $attribution['funnel']);
    }

    /**
     * Test executive management report aggregates all campaigns and ranks top revenue generators.
     */
    public function test_executive_management_attribution_report(): void
    {
        $report = $this->attributionService->getExecutiveAttributionReport();

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('top_campaigns_by_revenue', $report);
        $this->assertArrayHasKey('top_campaigns_by_leads', $report);
        $this->assertArrayHasKey('campaigns', $report);
    }

    /**
     * Test attribution endpoints return successful responses for web and API.
     */
    public function test_attribution_routes_accessible_to_authorized_users(): void
    {
        // 1. Single campaign attribution API
        $responseApi = $this->actingAs($this->user)->getJson(route('api.v1.email-campaigns.attribution', $this->campaign->id));
        $responseApi->assertOk();
        $responseApi->assertJsonStructure([
            'campaign',
            'engagement',
            'attribution' => [
                'campaign_generated_leads',
                'campaign_generated_inspections',
                'campaign_generated_opportunities',
                'campaign_generated_sales',
                'attributed_revenue',
            ],
            'funnel',
        ]);

        // 2. Executive report API
        $reportApi = $this->actingAs($this->user)->getJson(route('api.v1.email-campaigns.reports.attribution'));
        $reportApi->assertOk();
        $reportApi->assertJsonStructure([
            'summary',
            'top_campaigns_by_revenue',
            'top_campaigns_by_leads',
            'campaigns',
        ]);

        // 3. Web controller JSON request
        $webJson = $this->actingAs($this->user)->get(route('email-campaigns.attribution', $this->campaign->id), [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $webJson->assertOk();
    }
}
