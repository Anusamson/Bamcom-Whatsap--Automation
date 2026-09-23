<?php

namespace Tests\Unit\AI;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Message;
use App\Services\AI\ContextBuilder;
use App\Services\AI\KnowledgeService;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected ContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $knowledge = new KnowledgeService(new PropertyIntelligenceService);
        $this->builder = new ContextBuilder($knowledge);
    }

    public function test_can_build_full_prompt_messages_with_contact_and_history(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => 'Folake',
            'last_name' => 'Adeleke',
            'location' => 'Lekki Phase 1',
        ]);

        Lead::factory()->create([
            'contact_id' => $contact->id,
            'score' => 80,
            'property_interest' => 'Oasis Heights Plots',
        ]);

        $conversation = Conversation::factory()->hybrid()->open()->create([
            'contact_id' => $contact->id,
        ]);

        Message::factory()->inbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'body' => 'Good morning, do you have 500sqm plots available?',
        ]);

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'body' => 'Yes, we have prime plots at Oasis Heights Estate.',
        ]);

        $promptMessages = $this->builder->buildPromptMessages(
            conversation: $conversation,
            incomingUserMessage: 'Can I pay in installments?'
        );

        $this->assertCount(4, $promptMessages); // system, user, assistant, user
        $this->assertEquals('system', $promptMessages[0]['role']);
        $this->assertStringContainsString('Folake Adeleke', $promptMessages[0]['content']);
        $this->assertStringContainsString('STRICT OPERATIONAL GUARDRAILS', $promptMessages[0]['content']);
        $this->assertEquals('user', $promptMessages[1]['role']);
        $this->assertEquals('assistant', $promptMessages[2]['role']);
        $this->assertEquals('user', $promptMessages[3]['role']);
        $this->assertEquals('Can I pay in installments?', $promptMessages[3]['content']);
    }
}
