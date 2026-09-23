<?php

namespace Tests\Unit\AI;

use App\Enums\ConversationMode;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\ContextBuilder;
use App\Services\AI\IntentClassifier;
use App\Services\AI\KnowledgeService;
use App\Services\AI\Providers\MockAIProvider;
use App\Services\AI\Tools\BookInspectionTool;
use App\Services\AI\Tools\CalculatePaymentPlanTool;
use App\Services\AI\Tools\EscalateToHumanTool;
use App\Services\AI\Tools\SearchInventoryTool;
use App\Services\AI\Tools\ToolRegistry;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected AIOrchestrator $orchestrator;

    protected MockAIProvider $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockProvider = new MockAIProvider;
        $intelligence = new PropertyIntelligenceService;
        $knowledge = new KnowledgeService($intelligence);
        $contextBuilder = new ContextBuilder($knowledge);
        $intentClassifier = new IntentClassifier($this->mockProvider);

        $toolRegistry = new ToolRegistry([
            new SearchInventoryTool($intelligence),
            new BookInspectionTool,
            new EscalateToHumanTool,
            new CalculatePaymentPlanTool,
        ]);

        $this->orchestrator = new AIOrchestrator(
            provider: $this->mockProvider,
            contextBuilder: $contextBuilder,
            intentClassifier: $intentClassifier,
            knowledgeService: $knowledge,
            toolRegistry: $toolRegistry
        );
    }

    public function test_can_process_simple_conversation_turn(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->ai()->create(['contact_id' => $contact->id]);

        $response = $this->orchestrator->processConversationTurn(
            conversation: $conversation,
            incomingMessage: 'Hello, what does Bamcom offer?'
        );

        $this->assertNotEmpty($response->content);
        $this->assertEquals('mock', $response->provider);
    }

    public function test_can_process_turn_with_tool_calling_and_followup(): void
    {
        $estate = Estate::factory()->create(['name' => 'Oasis Heights', 'location' => 'Epe']);
        $prop = Property::factory()->create(['estate_id' => $estate->id, 'title' => 'Residential 500sqm Plot']);
        PropertyPrice::create(['property_id' => $prop->id, 'regular_price' => 12000000, 'is_active' => true]);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->ai()->create(['contact_id' => $contact->id]);

        // Incoming message asking for plots in Epe triggers search_inventory in MockAIProvider
        $response = $this->orchestrator->processConversationTurn(
            conversation: $conversation,
            incomingMessage: 'Do you have available plots in Epe?'
        );

        $this->assertTrue($response->hasToolCalls());
        $this->assertEquals('search_inventory', $response->firstToolCall()['name']);
        $this->assertEquals('tool_execution_complete', $response->finishReason);
    }

    public function test_handles_human_takeover_escalation_automatically(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->ai()->create(['contact_id' => $contact->id]);

        $response = $this->orchestrator->processConversationTurn(
            conversation: $conversation,
            incomingMessage: 'Please I want to speak to a human representative right now.'
        );

        $this->assertEquals('escalated', $response->finishReason);
        $this->assertEquals(ConversationMode::Human, $conversation->fresh()->mode);
        $this->assertStringContainsString('human representative has been assigned', $response->content);
    }
}
