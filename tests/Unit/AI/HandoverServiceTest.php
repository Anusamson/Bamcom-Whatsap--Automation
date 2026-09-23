<?php

namespace Tests\Unit\AI;

use App\Enums\AIIntent;
use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\HandoverTrigger;
use App\Enums\LeadTemperature;
use App\Jobs\SendWhatsAppResponseJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\HandoverRequiredNotification;
use App\Services\AI\DTOs\IntentResult;
use App\Services\AI\HandoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class HandoverServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HandoverService $service;

    protected Contact $contact;

    protected Conversation $conversation;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new HandoverService;

        $this->agent = User::factory()->create([
            'name' => 'Chioma Okeke',
            'email' => 'chioma@bamcom.ng',
            'role' => 'agent',
            'status' => 'active',
        ]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Babatunde',
            'last_name' => 'Fashola',
            'phone' => '+2348023456789',
        ]);

        $this->conversation = Conversation::create([
            'contact_id' => $this->contact->id,
            'mode' => ConversationMode::Ai,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'metadata' => [],
        ]);
    }

    public function test_detects_customer_request_trigger_from_keywords_and_intent(): void
    {
        // Keyword match
        $trigger = $this->service->detectTrigger($this->conversation, 'I want to speak with a human agent please');
        $this->assertSame(HandoverTrigger::CustomerRequest, $trigger);

        // Intent match
        $intentResult = new IntentResult(
            intent: AIIntent::HumanHandover,
            confidence: 0.95,
            requiresHumanTakeover: true
        );
        $triggerFromIntent = $this->service->detectTrigger($this->conversation, 'Please help', $intentResult);
        $this->assertSame(HandoverTrigger::CustomerRequest, $triggerFromIntent);
    }

    public function test_detects_complaint_trigger(): void
    {
        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'This whole thing is a scam and fraud! I will report you to the police and call my lawyer!'
        );

        $this->assertSame(HandoverTrigger::Complaint, $trigger);
        $this->assertSame('urgent', $trigger->priority());
    }

    public function test_detects_payment_readiness_trigger(): void
    {
        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'I am ready to pay the initial deposit for the Epe plot. Please send account details now.'
        );

        $this->assertSame(HandoverTrigger::PaymentReadiness, $trigger);
        $this->assertSame('urgent', $trigger->priority());
    }

    public function test_detects_negotiation_trigger(): void
    {
        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'Is the price negotiable? Can you give me a discount or reduce the price for 2 plots?'
        );

        $this->assertSame(HandoverTrigger::Negotiation, $trigger);
        $this->assertSame('high', $trigger->priority());
    }

    public function test_detects_unsupported_request_trigger(): void
    {
        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'Our corporate firm wants to submit a joint venture proposal for commercial development.'
        );

        $this->assertSame(HandoverTrigger::UnsupportedRequest, $trigger);
        $this->assertSame('normal', $trigger->priority());
    }

    public function test_detects_high_lead_score_trigger(): void
    {
        // Create hot lead for contact
        Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 85,
            'temperature' => LeadTemperature::Hot,
        ]);

        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'Tell me more about the payment plan for Phase 2.'
        );

        $this->assertSame(HandoverTrigger::HighLeadScore, $trigger);
        $this->assertSame('high', $trigger->priority());
    }

    public function test_detects_ai_uncertainty_trigger_when_confidence_is_low(): void
    {
        $intentResult = new IntentResult(
            intent: AIIntent::PropertyInquiry,
            confidence: 0.42,
            requiresHumanTakeover: false
        );

        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'random ambiguous gibberish xyz',
            $intentResult
        );

        $this->assertSame(HandoverTrigger::AiUncertainty, $trigger);
        $this->assertSame('normal', $trigger->priority());
    }

    public function test_returns_null_when_message_is_normal_inquiry(): void
    {
        $intentResult = new IntentResult(
            intent: AIIntent::PropertyInquiry,
            confidence: 0.92,
            requiresHumanTakeover: false
        );

        $trigger = $this->service->detectTrigger(
            $this->conversation,
            'Hello, what is the location of Bamcom Epe Waterfront?',
            $intentResult
        );

        $this->assertNull($trigger);
    }

    public function test_execute_handover_pauses_ai_notifies_rep_and_creates_crm_task(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        $result = $this->service->executeHandover(
            $this->conversation,
            HandoverTrigger::Negotiation,
            ['reason' => 'Customer asked for 15% discount on commercial plot']
        );

        $this->assertSame('escalated', $result['status']);
        $this->assertSame('negotiation', $result['trigger']);
        $this->assertSame('human', $result['mode']);
        $this->assertSame($this->agent->name, $result['assigned_user']);
        $this->assertNotEmpty($result['task_id']);

        // Check conversation state
        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertSame(ConversationStatus::Open, $this->conversation->status);
        $this->assertSame($this->agent->id, $this->conversation->assigned_user_id);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
        $this->assertSame('negotiation', $this->conversation->metadata['handover']['trigger']);
        $this->assertSame('high', $this->conversation->metadata['handover']['priority']);

        // Check Notification dispatched
        Notification::assertSentTo(
            $this->agent,
            HandoverRequiredNotification::class,
            function (HandoverRequiredNotification $notification) {
                return $notification->trigger === HandoverTrigger::Negotiation
                    && $notification->conversation->id === $this->conversation->id;
            }
        );

        // Check CRM Follow-Up Task
        $task = Activity::find($result['task_id']);
        $this->assertNotNull($task);
        $this->assertSame('task', $task->activity_type);
        $this->assertSame('high', $task->properties['priority']);
        $this->assertSame($this->conversation->id, $task->properties['conversation_id']);

        // Check Audit Activity Log
        $audit = Activity::where('activity_type', 'ai_handover_executed')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertSame('negotiation', $audit->properties['trigger']);
        $this->assertSame($this->agent->id, $audit->properties['assigned_user_id']);

        // Check Customer WhatsApp Acknowledgment queued
        Queue::assertPushed(SendWhatsAppResponseJob::class, function (SendWhatsAppResponseJob $job) {
            return $job->conversation->id === $this->conversation->id
                && str_contains(strtolower($job->content), 'sales');
        });
    }

    public function test_execute_handover_retains_existing_assigned_user(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);

        $customAgent = User::factory()->create([
            'role' => 'agent',
            'status' => 'active',
            'name' => 'Existing Rep',
        ]);

        $this->conversation->update(['assigned_user_id' => $customAgent->id]);

        $result = $this->service->executeHandover(
            $this->conversation,
            HandoverTrigger::CustomerRequest
        );

        $this->assertSame('Existing Rep', $result['assigned_user']);
        $this->conversation->refresh();
        $this->assertSame($customAgent->id, $this->conversation->assigned_user_id);
    }

    public function test_resume_ai_returns_conversation_to_ai_mode(): void
    {
        // First pause conversation
        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => [
                'ai_paused' => true,
                'handover' => ['trigger' => 'customer_request'],
            ],
        ]);

        $updated = $this->service->resumeAi(
            $this->conversation,
            ConversationMode::Ai,
            $this->agent
        );

        $this->assertSame(ConversationMode::Ai, $updated->mode);
        $this->assertFalse($updated->metadata['ai_paused']);
        $this->assertSame($this->agent->id, $updated->metadata['ai_resumed_by_user_id']);

        $resumedActivity = Activity::where('activity_type', 'ai_resumed')->latest()->first();
        $this->assertNotNull($resumedActivity);
        $this->assertSame('ai', $resumedActivity->properties['resumed_mode']);
        $this->assertSame($this->agent->id, $resumedActivity->properties['resumed_by_user_id']);
    }

    public function test_resume_ai_returns_conversation_to_hybrid_mode(): void
    {
        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => ['ai_paused' => true],
        ]);

        $updated = $this->service->resumeAi(
            $this->conversation,
            ConversationMode::Hybrid,
            $this->agent
        );

        $this->assertSame(ConversationMode::Hybrid, $updated->mode);
        $this->assertFalse($updated->metadata['ai_paused']);
    }

    public function test_resume_ai_rejects_human_mode_with_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->resumeAi(
            $this->conversation,
            ConversationMode::Human,
            $this->agent
        );
    }
}
