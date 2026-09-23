<?php

namespace Tests\Feature\AI;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Jobs\SendWhatsAppResponseJob;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\ConversationAiPipeline;
use App\Services\AI\Tools\RequestHumanHandoverTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HandoverFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $agentUser;

    protected User $unauthorizedUser;

    protected Contact $contact;

    protected Conversation $conversation;

    protected WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.default_provider', 'mock');
        Config::set('whatsapp.verify_token', 'test_verify_token');

        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'sanctum']);
        }

        $this->agentUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'name' => 'Emeka Okafor',
            'status' => 'active',
        ]);
        $this->agentUser->givePermissionTo([
            PermissionEnum::ConversationsView->value,
            PermissionEnum::ConversationsManage->value,
            PermissionEnum::ConversationsAssign->value,
            PermissionEnum::MessagesSend->value,
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
            'status' => 'active',
        ]);

        $this->account = WhatsAppAccount::factory()->default()->create([
            'phone_number_id' => '109876543210',
            'display_phone_number' => '+2348000000000',
        ]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Tunde',
            'last_name' => 'Bakare',
            'phone' => '+2348011223344',
        ]);

        $this->conversation = Conversation::create([
            'contact_id' => $this->contact->id,
            'whatsapp_account_id' => $this->account->id,
            'mode' => ConversationMode::Ai,
            'status' => ConversationStatus::Open,
            'channel' => 'whatsapp',
            'metadata' => [],
        ]);
    }

    public function test_incoming_negotiation_message_triggers_handover_via_pipeline(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'Can you give me a discount or reduce the price if I buy two plots in Epe?',
            'status' => 'received',
            'sender_type' => 'contact',
            'sender_id' => $this->contact->id,
        ]);

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $message);

        $this->assertSame('escalated', $result['status']);
        $this->assertSame('negotiation', $result['trigger']);
        $this->assertSame('human', $result['mode']);

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
        $this->assertFalse($this->conversation->metadata['ai_processing']);
        $this->assertSame('negotiation', $this->conversation->metadata['handover']['trigger']);

        // CRM follow up task and audit activity
        $task = Activity::where('activity_type', 'task')->latest()->first();
        $this->assertNotNull($task);
        $this->assertSame('high', $task->properties['priority']);

        $audit = Activity::where('activity_type', 'ai_handover_executed')->latest()->first();
        $this->assertNotNull($audit);

        Queue::assertPushed(SendWhatsAppResponseJob::class);
    }

    public function test_incoming_complaint_message_triggers_urgent_handover_via_pipeline(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'This company is a complete scam and fraud! I will report you to the police!',
            'status' => 'received',
            'sender_type' => 'contact',
            'sender_id' => $this->contact->id,
        ]);

        /** @var ConversationAiPipeline $pipeline */
        $pipeline = app(ConversationAiPipeline::class);
        $result = $pipeline->processTurn($this->conversation, $message);

        $this->assertSame('escalated', $result['status']);
        $this->assertSame('complaint', $result['trigger']);

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);

        $task = Activity::where('activity_type', 'task')->latest()->first();
        $this->assertNotNull($task);
        $this->assertSame('urgent', $task->properties['priority']);
    }

    public function test_bamcom_sales_agent_can_call_request_human_handover_tool(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        $tool = new RequestHumanHandoverTool;
        $result = $tool->execute([
            'reason' => 'Client requires customized joint venture deed review',
            'urgency' => 'high',
        ], [
            'conversation' => $this->conversation,
            'contact' => $this->contact,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('human', $result['conversation_mode']);

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
    }

    public function test_authorized_user_can_resume_ai_via_inbox_controller(): void
    {
        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => [
                'ai_paused' => true,
                'handover' => ['trigger' => 'customer_request'],
            ],
        ]);

        $response = $this->actingAs($this->agentUser)
            ->post(route('inbox.resume-ai', $this->conversation), [
                'target_mode' => 'ai',
                'notes' => 'Customer query answered, returning to AI',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Ai, $this->conversation->mode);
        $this->assertFalse($this->conversation->metadata['ai_paused']);
        $this->assertSame($this->agentUser->id, $this->conversation->metadata['ai_resumed_by_user_id']);

        $audit = Activity::where('activity_type', 'ai_resumed')->latest()->first();
        $this->assertNotNull($audit);
        $this->assertSame('ai', $audit->properties['resumed_mode']);
    }

    public function test_authorized_user_can_resume_hybrid_via_inbox_controller(): void
    {
        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => ['ai_paused' => true],
        ]);

        $response = $this->actingAs($this->agentUser)
            ->post(route('inbox.resume-ai', $this->conversation), [
                'target_mode' => 'hybrid',
            ]);

        $response->assertRedirect();
        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Hybrid, $this->conversation->mode);
        $this->assertFalse($this->conversation->metadata['ai_paused']);
    }

    public function test_unauthorized_user_is_forbidden_from_resuming_ai(): void
    {
        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => ['ai_paused' => true],
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->post(route('inbox.resume-ai', $this->conversation), [
                'target_mode' => 'ai',
            ]);

        $response->assertForbidden();

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
    }

    public function test_authorized_user_can_resume_ai_via_api_v1(): void
    {
        Sanctum::actingAs($this->agentUser);

        $this->conversation->update([
            'mode' => ConversationMode::Human,
            'metadata' => ['ai_paused' => true],
        ]);

        $response = $this->postJson(route('api.v1.conversations.resume-ai', $this->conversation), [
            'target_mode' => 'ai',
            'notes' => 'API resumption to autonomous mode',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.mode', 'ai');

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Ai, $this->conversation->mode);
        $this->assertFalse($this->conversation->metadata['ai_paused']);
    }

    public function test_authorized_user_can_trigger_manual_handover_via_inbox(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        $response = $this->actingAs($this->agentUser)
            ->post(route('inbox.handover', $this->conversation), [
                'trigger' => 'customer_request',
                'reason' => 'Customer called the direct phone line requesting manual agent handling',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
        $this->assertSame('customer_request', $this->conversation->metadata['handover']['trigger']);
    }

    public function test_authorized_user_can_trigger_manual_handover_via_api(): void
    {
        Queue::fake([SendWhatsAppResponseJob::class]);
        Notification::fake();

        Sanctum::actingAs($this->agentUser);

        $response = $this->postJson(route('api.v1.conversations.handover', $this->conversation), [
            'trigger' => 'negotiation',
            'reason' => 'VIP client requested corporate bulk discount',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.trigger', 'negotiation');
        $response->assertJsonPath('data.mode', 'human');

        $this->conversation->refresh();
        $this->assertSame(ConversationMode::Human, $this->conversation->mode);
        $this->assertTrue($this->conversation->metadata['ai_paused']);
    }
}
