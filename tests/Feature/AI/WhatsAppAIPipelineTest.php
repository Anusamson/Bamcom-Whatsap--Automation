<?php

namespace Tests\Feature\AI;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Jobs\ProcessConversationAiTurn;
use App\Jobs\SendWhatsAppResponseJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\AI\BamcomSalesAgent;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\ConversationAiPipeline;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\IntentClassifier;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\Conversation\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WhatsAppAIPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppAccount $account;

    protected Contact $contact;

    protected Conversation $conversation;

    protected MockAIProvider $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.default_provider', 'mock');
        Config::set('whatsapp.verify_token', 'test_verify_token');
        Config::set('whatsapp.app_secret', 'test_secret_signature');

        $this->account = WhatsAppAccount::factory()->default()->create([
            'phone_number_id' => '109876543210',
            'display_phone_number' => '+2348000000000',
        ]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Adewale',
            'last_name' => 'Babatunde',
            'phone' => '+2348031234567',
        ]);

        $this->conversation = Conversation::create([
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'mode' => ConversationMode::Ai,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'last_message_at' => now(),
            'unread_count' => 1,
            'metadata' => [],
        ]);

        $this->mockProvider = new MockAIProvider;
        $this->app->instance(MockAIProvider::class, $this->mockProvider);
        $this->app->instance(AIProviderInterface::class, $this->mockProvider);
        $this->app->forgetInstance(BamcomSalesAgent::class);
        $this->app->forgetInstance(ConversationAiPipeline::class);
        $this->app->forgetInstance(IntentClassifier::class);
    }

    protected function createIncomingMessage(string $body): Message
    {
        return Message::create([
            'conversation_id' => $this->conversation->id,
            'contact_id' => $this->contact->id,
            'sender_type' => 'contact',
            'meta_message_id' => 'wamid.'.uniqid(),
            'direction' => 'inbound',
            'sender_phone' => '2348031234567',
            'recipient_phone' => '+2348000000000',
            'type' => 'text',
            'body' => $body,
            'delivery_status' => 'received',
            'is_read' => false,
            'sent_at' => now(),
        ]);
    }

    public function test_ai_mode_generates_and_queues_outbound_whatsapp_response(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        $this->mockProvider->setNextResponse(new AIResponse(
            content: "Welcome to *Bamcom Properties*! 🏢\nWe have prime verified plots at *Oasis Heights, Epe* for *₦12,500,000*.",
            toolCalls: [],
            usage: ['total_tokens' => 80],
            provider: 'mock'
        ));

        $incoming = $this->createIncomingMessage('Hello, what properties are available in Epe?');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(ConversationMode::Ai->value, $result['mode']);
        $this->assertEquals('queued_outbound_response', $result['action_taken']);

        // Assert job was queued to send outbound WhatsApp message
        Queue::assertPushed(SendWhatsAppResponseJob::class, function (SendWhatsAppResponseJob $job) {
            return $job->conversation->id === $this->conversation->id
                && str_contains($job->content, 'Oasis Heights, Epe')
                && str_contains($job->content, '₦12,500,000');
        });

        // Assert conversation state is clean and open
        $refreshed = $this->conversation->fresh();
        $this->assertEquals(ConversationStatus::Open, $refreshed->status);
        $this->assertFalse($refreshed->metadata['ai_processing'] ?? false);

        // Assert activity log was created
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'ai_response_dispatched',
        ]);
    }

    public function test_human_mode_suppresses_ai_generation_and_outbound_response(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        $this->conversation->update(['mode' => ConversationMode::Human]);

        $incoming = $this->createIncomingMessage('Can I get a discount on the Lekki land?');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals(ConversationMode::Human->value, $result['mode']);
        $this->assertEquals('human_mode_suppressed', $result['action_taken']);

        // Assert no outbound message was queued
        Queue::assertNotPushed(SendWhatsAppResponseJob::class);

        // Assert no AI suggestion was stored
        $refreshed = $this->conversation->fresh();
        $this->assertNull($refreshed->metadata['ai_suggestion'] ?? null);
    }

    public function test_hybrid_mode_generates_suggestion_in_metadata_without_outbound_dispatch(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        $this->conversation->update(['mode' => ConversationMode::Hybrid]);

        $this->mockProvider->setNextResponse(new AIResponse(
            content: 'Hello! Our 500 SQM plots at *Oasis Heights, Epe* start at *₦12,500,000* with 20% initial deposit.',
            toolCalls: [],
            usage: ['total_tokens' => 90],
            provider: 'mock'
        ));

        $incoming = $this->createIncomingMessage('Tell me about the payment plan for Epe.');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(ConversationMode::Hybrid->value, $result['mode']);
        $this->assertEquals('saved_suggestion', $result['action_taken']);

        // Assert NO outbound WhatsApp message was sent to the customer
        Queue::assertNotPushed(SendWhatsAppResponseJob::class);

        // Assert suggestion was saved in conversation metadata for human rep
        $refreshed = $this->conversation->fresh();
        $this->assertNotNull($refreshed->metadata['ai_suggestion'] ?? null);
        $this->assertStringContainsString('Oasis Heights, Epe', $refreshed->metadata['ai_suggestion']['content']);
        $this->assertFalse($refreshed->metadata['ai_processing'] ?? false);

        // Assert activity was recorded
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'ai_suggestion_created',
        ]);
    }

    public function test_human_handover_intent_transitions_conversation_to_human_mode(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        $incoming = $this->createIncomingMessage('I need to speak with a human sales agent right now please');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('escalated', $result['status']);
        $this->assertEquals(ConversationMode::Human->value, $result['mode']);
        $this->assertEquals('human_handover_executed', $result['action_taken']);

        $refreshed = $this->conversation->fresh();
        $this->assertEquals(ConversationMode::Human, $refreshed->mode);
        $this->assertEquals(ConversationStatus::Open, $refreshed->status);

        // Assert notification sent to customer
        Queue::assertPushed(SendWhatsAppResponseJob::class, function (SendWhatsAppResponseJob $job) {
            return str_contains($job->content, 'human representative has been assigned');
        });

        // Assert activity logged
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'human_handover_requested',
        ]);
    }

    public function test_safety_validator_blocks_unsafe_content_and_escalates_to_human(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        // Model attempts to output prohibited financial guarantee
        $this->mockProvider->setNextResponse(new AIResponse(
            content: 'Invest now and you will get a 100% return guaranteed profit within 30 days!',
            toolCalls: [],
            usage: ['total_tokens' => 50],
            provider: 'mock'
        ));

        $incoming = $this->createIncomingMessage('Can you guarantee returns on this estate?');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals(ConversationMode::Human->value, $result['mode']);
        $this->assertEquals('safety_violation_escalated', $result['action_taken']);

        // Assert escalated to human mode
        $refreshed = $this->conversation->fresh();
        $this->assertEquals(ConversationMode::Human, $refreshed->mode);
        $this->assertEquals(ConversationStatus::Open, $refreshed->status);

        // Assert prohibited message was NEVER queued; safe fallback queued instead
        Queue::assertPushed(SendWhatsAppResponseJob::class, function (SendWhatsAppResponseJob $job) {
            return ! str_contains($job->content, 'guaranteed profit')
                && str_contains($job->content, 'senior property consultant has been assigned');
        });

        // Assert safety rejection was logged
        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $this->conversation->id,
            'status' => 'safety_rejected',
        ]);

        $this->assertDatabaseHas('activities', [
            'activity_type' => 'ai_safety_violation',
        ]);
    }

    public function test_failure_handling_clears_processing_flag_and_keeps_conversation_open_when_ai_fails(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        // Simulate provider exception / timeout
        $this->mockProvider->setResponseResolver(function () {
            throw new RuntimeException('Gemini API 503 Service Unavailable: Rate Limit Exceeded');
        });

        $incoming = $this->createIncomingMessage('Can you check availability of plots?');

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $incoming);

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('failure_handled_and_escalated', $result['action_taken']);

        $refreshed = $this->conversation->fresh();

        // Customer is NEVER left in endless processing state:
        $this->assertEquals(ConversationStatus::Open, $refreshed->status);
        $this->assertFalse($refreshed->metadata['ai_processing'] ?? false);
        $this->assertNotNull($refreshed->metadata['ai_last_error'] ?? null);
        $this->assertStringContainsString('Rate Limit Exceeded', $refreshed->metadata['ai_last_error']['message']);

        // Escalate to human
        $this->assertEquals(ConversationMode::Human, $refreshed->mode);

        // Audit failure in execution log
        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $this->conversation->id,
            'status' => 'failed',
        ]);

        // Customer received polite fallback message
        Queue::assertPushed(SendWhatsAppResponseJob::class, function (SendWhatsAppResponseJob $job) {
            return str_contains($job->content, 'sales advisor will reply shortly');
        });
    }

    public function test_incoming_webhook_dispatches_ai_turn_job(): void
    {
        Queue::fake([ProcessConversationAiTurn::class, SendWhatsAppResponseJob::class]);

        $messageId = 'wamid.WEBHOOK_DISPATCH_TEST_1';
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Folake Adeleke'], 'wa_id' => '2348055667788'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348055667788',
                                        'id' => $messageId,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'I want to schedule an inspection for Oasis Heights.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $rawBody, 'test_secret_signature');

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertStatus(200);

        // Assert ProcessConversationAiTurn was dispatched
        Queue::assertPushed(ProcessConversationAiTurn::class, function (ProcessConversationAiTurn $job) {
            return str_contains((string) $job->message->body, 'schedule an inspection');
        });
    }

    public function test_send_whatsapp_response_job_invokes_whatsapp_message_service(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348031234567', 'wa_id' => '2348031234567']],
                'messages' => [['id' => 'wamid.DISPATCHED_TEST_ID']],
            ], 200),
        ]);

        $job = new SendWhatsAppResponseJob(
            conversation: $this->conversation,
            content: 'Hello from Bamcom AI Sales Agent!'
        );

        $message = $job->handle(app(ConversationService::class));

        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('outbound', $message->direction);
        $this->assertEquals('system', $message->sender_type);
        $this->assertEquals('Hello from Bamcom AI Sales Agent!', $message->body);
        $this->assertEquals('wamid.DISPATCHED_TEST_ID', $message->meta_message_id);
    }
}
