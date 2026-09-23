<?php

namespace Tests\Feature\Conversation;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\LeadTemperature;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Estate;
use App\Models\Lead;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConversationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $agentUser;

    protected User $unauthorizedUser;

    protected WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.access_token', 'test_access_token');
        Config::set('whatsapp.phone_number_id', '109876543210');

        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        $this->adminUser = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        $this->adminUser->givePermissionTo([
            PermissionEnum::ConversationsView->value,
            PermissionEnum::ConversationsManage->value,
            PermissionEnum::ConversationsAssign->value,
            PermissionEnum::MessagesSend->value,
        ]);

        $this->agentUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
        ]);
        $this->agentUser->givePermissionTo([
            PermissionEnum::ConversationsView->value,
            PermissionEnum::ConversationsManage->value,
            PermissionEnum::ConversationsAssign->value,
            PermissionEnum::MessagesSend->value,
        ]);

        $this->unauthorizedUser = User::factory()->create([
            'role' => UserRole::CustomerSupport,
        ]);

        $this->account = WhatsAppAccount::factory()->default()->create([
            'phone_number_id' => '109876543210',
            'display_phone_number' => '+2348000000000',
        ]);
    }

    public function test_can_render_team_inbox_with_conversations_and_filter_counts(): void
    {
        $contact1 = Contact::factory()->create(['first_name' => 'Aisha', 'last_name' => 'Bello']);
        $contact2 = Contact::factory()->create(['first_name' => 'Babajide', 'last_name' => 'Sanwo']);

        $conv1 = Conversation::factory()->ai()->open()->create([
            'contact_id' => $contact1->id,
            'assigned_user_id' => $this->agentUser->id,
            'unread_count' => 2,
        ]);
        $conv2 = Conversation::factory()->human()->closed()->create([
            'contact_id' => $contact2->id,
            'assigned_user_id' => null,
            'unread_count' => 0,
        ]);

        $response = $this->actingAs($this->agentUser)->get('/inbox');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Inbox')
            ->has('conversations.data', 2)
            ->where('counts.all', 2)
            ->where('counts.mine', 1)
            ->where('counts.unassigned', 1)
            ->where('counts.unread', 1)
            ->where('counts.ai', 1)
            ->where('counts.human', 1)
            ->where('counts.hybrid', 0)
            ->has('activeConversation')
            ->has('users')
            ->has('templates')
            ->has('estates')
        );
    }

    public function test_can_filter_inbox_by_all_filter_tabs(): void
    {
        $contactMine = Contact::factory()->create();
        $contactUnassigned = Contact::factory()->create();
        $contactHot = Contact::factory()->create();

        // 1. Mine (assigned to agent)
        Conversation::factory()->hybrid()->open()->create([
            'contact_id' => $contactMine->id,
            'assigned_user_id' => $this->agentUser->id,
            'unread_count' => 0,
        ]);

        // 2. Unassigned and Unread
        Conversation::factory()->ai()->open()->create([
            'contact_id' => $contactUnassigned->id,
            'assigned_user_id' => null,
            'unread_count' => 5,
        ]);

        // 3. Hot Lead
        $hotConv = Conversation::factory()->human()->open()->create([
            'contact_id' => $contactHot->id,
            'assigned_user_id' => $this->adminUser->id,
        ]);
        Lead::factory()->create([
            'contact_id' => $contactHot->id,
            'score' => 85,
            'temperature' => LeadTemperature::Hot,
        ]);

        // Test Mine
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=mine')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test Unassigned
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=unassigned')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test Unread
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=unread')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test AI
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=ai')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test Human
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=human')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test Hybrid
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=hybrid')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));

        // Test Hot Leads
        $this->actingAs($this->agentUser)
            ->get('/inbox?tab=hot_leads')
            ->assertInertia(fn (Assert $page) => $page->has('conversations.data', 1));
    }

    public function test_can_send_outbound_message_from_inbox(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348012345678', 'wa_id' => '2348012345678']],
                'messages' => [['id' => 'wamid.HBgLMjM0ODAxMjM0NTY3OBUCMRIA']],
            ], 200),
        ]);

        $contact = Contact::factory()->create(['phone' => '+2348012345678']);
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->agentUser)
            ->post("/inbox/{$conversation->id}/messages", [
                'body' => 'Hello from Bamcom Inbox team!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'body' => 'Hello from Bamcom Inbox team!',
            'direction' => 'outbound',
            'delivery_status' => 'sent',
        ]);
    }

    public function test_can_update_conversation_mode_from_inbox(): void
    {
        $conversation = Conversation::factory()->ai()->create();

        $response = $this->actingAs($this->agentUser)
            ->patch("/inbox/{$conversation->id}/mode", [
                'mode' => ConversationMode::Human->value,
            ]);

        $response->assertRedirect();
        $this->assertEquals(ConversationMode::Human, $conversation->fresh()->mode);
    }

    public function test_can_update_conversation_status_from_inbox(): void
    {
        $conversation = Conversation::factory()->open()->create();

        $response = $this->actingAs($this->agentUser)
            ->patch("/inbox/{$conversation->id}/status", [
                'status' => ConversationStatus::Closed->value,
            ]);

        $response->assertRedirect();
        $this->assertEquals(ConversationStatus::Closed, $conversation->fresh()->status);
        $this->assertNotNull($conversation->fresh()->closed_at);
    }

    public function test_can_assign_agent_from_inbox(): void
    {
        $conversation = Conversation::factory()->create(['assigned_user_id' => null]);

        $response = $this->actingAs($this->adminUser)
            ->patch("/inbox/{$conversation->id}/assign", [
                'assigned_user_id' => $this->agentUser->id,
            ]);

        $response->assertRedirect();
        $this->assertEquals($this->agentUser->id, $conversation->fresh()->assigned_user_id);
    }

    public function test_can_mark_conversation_as_read_from_inbox(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'unread_count' => 3,
        ]);

        $msg = Message::factory()->inbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->agentUser)
            ->post("/inbox/{$conversation->id}/read");

        $response->assertRedirect();
        $this->assertEquals(0, $conversation->fresh()->unread_count);
        $this->assertTrue($msg->fresh()->is_read);
    }

    public function test_can_schedule_inspection_from_inbox(): void
    {
        $estate = Estate::factory()->create(['name' => 'Oasis Heights Estate']);
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $payload = [
            'estate_name' => $estate->name,
            'inspection_date' => '2026-10-15',
            'inspection_time' => '11:00 AM',
            'inspector_id' => $this->agentUser->id,
            'notes' => 'Pickup at toll gate',
        ];

        $response = $this->actingAs($this->agentUser)
            ->post("/inbox/{$conversation->id}/inspections", $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'inspection_scheduled',
            'user_id' => $this->agentUser->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_inbox(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get('/inbox');
        $response->assertStatus(403);
    }
}
