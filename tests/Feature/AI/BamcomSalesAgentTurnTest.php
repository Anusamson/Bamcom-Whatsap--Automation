<?php

namespace Tests\Feature\AI;

use App\Enums\ConversationMode;
use App\Enums\LeadTemperature;
use App\Models\AIExecutionLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Lead;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\BamcomSalesAgent;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Providers\MockAIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BamcomSalesAgentTurnTest extends TestCase
{
    use RefreshDatabase;

    protected BamcomSalesAgent $agent;

    protected MockAIProvider $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.default_provider' => 'mock']);

        $this->mockProvider = new MockAIProvider;
        $this->app->instance(MockAIProvider::class, $this->mockProvider);

        // Bind mock provider to container
        $this->app->bind(AIProviderInterface::class, fn () => $this->mockProvider);

        $this->agent = app(BamcomSalesAgent::class);
    }

    public function test_conversational_turn_without_tool_calls_produces_answer_and_logs_execution(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Chinedu',
            'last_name' => 'Eze',
        ]);

        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'mode' => ConversationMode::Ai,
        ]);

        $this->mockProvider->setNextResponse(new AIResponse(
            content: 'Hello Chinedu! Welcome to *Bamcom Properties*. How can I assist you with your land investment today?',
            toolCalls: [],
            usage: ['total_tokens' => 120],
            provider: 'mock',
            finishReason: 'stop'
        ));

        $response = $this->agent->handleTurn($conversation, 'Hello, good afternoon!');

        $this->assertStringContainsString('Welcome to *Bamcom Properties*', $response->content);
        $this->assertEquals('mock', $response->provider);

        // Verify execution log is saved in database
        $this->assertDatabaseHas('ai_execution_logs', [
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'agent_name' => 'BamcomSalesAgent',
            'provider' => 'mock',
            'user_message' => 'Hello, good afternoon!',
            'status' => 'success',
        ]);
    }

    public function test_tool_calling_turn_executes_search_properties_and_synthesizes_response(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Grace Palms Scheme',
            'location' => 'Ibeju-Lekki',
            'status' => 'active',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Grace Palms 500sqm Plot',
            'available_units' => 6,
            'availability' => 'available',
            'status' => 'published',
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 20_000_000,
            'is_active' => true,
        ]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        // First pass: Model requests searchProperties
        $this->mockProvider->setNextResponse(new AIResponse(
            content: '',
            toolCalls: [
                [
                    'name' => 'searchProperties',
                    'arguments' => ['location' => 'Ibeju-Lekki'],
                ],
            ],
            usage: ['total_tokens' => 150],
            provider: 'mock',
            finishReason: 'tool_calls'
        ));

        // Second pass: Model synthesizes final WhatsApp response with tool facts
        $this->mockProvider->setFollowupResponse(new AIResponse(
            content: 'We have *Grace Palms 500sqm Plot* available in *Ibeju-Lekki* for *₦20,000,000.00* with 6 units remaining!',
            toolCalls: [],
            usage: ['total_tokens' => 210],
            provider: 'mock',
            finishReason: 'stop'
        ));

        $response = $this->agent->handleTurn($conversation, 'Do you have land in Ibeju Lekki?');

        $this->assertStringContainsString('Grace Palms 500sqm Plot', $response->content);
        $this->assertStringContainsString('₦20,000,000.00', $response->content);

        // Verify tool execution was audited
        $log = AIExecutionLog::query()
            ->where('conversation_id', $conversation->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->tool_calls);
        $this->assertEquals('searchProperties', $log->tool_calls[0]['tool']);
        $this->assertNotNull($log->tool_results);
        $this->assertTrue($log->tool_results['searchProperties']['success']);
    }

    public function test_inspection_scheduling_turn_creates_crm_activity_and_upgrades_lead(): void
    {
        $contact = Contact::factory()->create();
        $lead = Lead::factory()->create([
            'contact_id' => $contact->id,
            'temperature' => LeadTemperature::Warm,
        ]);

        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $inspectionDate = now()->addDays(2)->format('Y-m-d');

        // First pass: Model requests scheduleInspection
        $this->mockProvider->setNextResponse(new AIResponse(
            content: '',
            toolCalls: [
                [
                    'name' => 'scheduleInspection',
                    'arguments' => [
                        'preferred_date' => $inspectionDate,
                        'preferred_time' => '10:00 AM',
                    ],
                ],
            ],
            usage: ['total_tokens' => 180],
            provider: 'mock',
            finishReason: 'tool_calls'
        ));

        // Second pass: Final confirmation
        $this->mockProvider->setFollowupResponse(new AIResponse(
            content: "Your site inspection is booked for *{$inspectionDate}* at *10:00 AM*. Our vehicle leaves from Lekki Phase 1!",
            toolCalls: [],
            usage: ['total_tokens' => 120],
            provider: 'mock',
            finishReason: 'stop'
        ));

        $response = $this->agent->handleTurn($conversation, 'Can I visit this Saturday for inspection?');

        $this->assertStringContainsString('inspection is booked', $response->content);

        // Verify database state: activity created and lead promoted to Hot
        $this->assertDatabaseHas('activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'inspection',
        ]);

        $lead->refresh();
        $this->assertEquals(LeadTemperature::Hot, $lead->temperature);
    }

    public function test_human_handover_turn_transitions_conversation_mode(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'mode' => ConversationMode::Ai,
        ]);

        // Model requests requestHumanHandover
        $this->mockProvider->setNextResponse(new AIResponse(
            content: '',
            toolCalls: [
                [
                    'name' => 'requestHumanHandover',
                    'arguments' => ['reason' => 'Client needs legal survey verification'],
                ],
            ],
            usage: ['total_tokens' => 110],
            provider: 'mock',
            finishReason: 'tool_calls'
        ));

        $this->mockProvider->setFollowupResponse(new AIResponse(
            content: 'I have transferred you to our human sales representative who will assist you shortly.',
            toolCalls: [],
            usage: ['total_tokens' => 90],
            provider: 'mock',
            finishReason: 'stop'
        ));

        $this->agent->handleTurn($conversation, 'Please let me speak to a human.');

        $conversation->refresh();
        $this->assertEquals(ConversationMode::Human, $conversation->mode);
    }
}
