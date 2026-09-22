<?php

namespace Tests\Feature\Conversation;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConversationApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->unauthorizedUser = User::factory()->create([
            'role' => UserRole::SalesExecutive,
        ]);
    }

    public function test_can_list_conversations_with_filtering_and_pagination(): void
    {
        Sanctum::actingAs($this->adminUser);

        $contact1 = Contact::factory()->create(['first_name' => 'Amina', 'last_name' => 'Danjuma']);
        $contact2 = Contact::factory()->create(['first_name' => 'Chidi', 'last_name' => 'Okeke']);

        Conversation::factory()->ai()->open()->create(['contact_id' => $contact1->id, 'subject' => 'Epe Inquiries']);
        Conversation::factory()->human()->closed()->create(['contact_id' => $contact2->id, 'subject' => 'Lekki Negotiation']);

        // 1. Filter by mode: ai
        $response = $this->getJson('/api/v1/conversations?mode=ai');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mode', 'ai')
            ->assertJsonPath('meta.total', 1);

        // 2. Filter by status: closed
        $response = $this->getJson('/api/v1/conversations?status=closed');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'closed');

        // 3. Search by contact name
        $response = $this->getJson('/api/v1/conversations?search=Amina');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact.first_name', 'Amina');
    }

    public function test_can_create_new_conversation_for_contact(): void
    {
        Sanctum::actingAs($this->adminUser);

        $contact = Contact::factory()->create();
        $rep = User::factory()->create();

        $payload = [
            'contact_id' => $contact->id,
            'mode' => ConversationMode::Human->value,
            'assigned_user_id' => $rep->id,
            'subject' => 'Commercial plot acquisition',
        ];

        $response = $this->postJson('/api/v1/conversations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.contact_id', $contact->id)
            ->assertJsonPath('data.mode', 'human')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.assigned_user_id', $rep->id);

        $this->assertDatabaseHas('conversations', [
            'contact_id' => $contact->id,
            'mode' => 'human',
            'status' => 'open',
            'assigned_user_id' => $rep->id,
            'subject' => 'Commercial plot acquisition',
        ]);
    }

    public function test_can_show_conversation_details(): void
    {
        Sanctum::actingAs($this->adminUser);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'body' => 'Hello Bamcom team!',
        ]);

        $response = $this->getJson("/api/v1/conversations/{$conversation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.contact.id', $contact->id)
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_can_update_conversation_mode(): void
    {
        Sanctum::actingAs($this->adminUser);

        $conversation = Conversation::factory()->ai()->create();

        // Switch from AI to Human takeover
        $response = $this->patchJson("/api/v1/conversations/{$conversation->id}/mode", [
            'mode' => ConversationMode::Human->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.mode', 'human');

        $this->assertEquals(ConversationMode::Human, $conversation->fresh()->mode);
    }

    public function test_can_update_conversation_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        $conversation = Conversation::factory()->open()->create();

        // Close conversation
        $response = $this->patchJson("/api/v1/conversations/{$conversation->id}/status", [
            'status' => ConversationStatus::Closed->value,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        $conversation->refresh();
        $this->assertEquals(ConversationStatus::Closed, $conversation->status);
        $this->assertNotNull($conversation->closed_at);
    }

    public function test_can_assign_conversation_to_representative(): void
    {
        Sanctum::actingAs($this->adminUser);

        $conversation = Conversation::factory()->create(['assigned_user_id' => null]);
        $salesRep = User::factory()->create(['name' => 'Tunde Adeyemi']);

        $response = $this->patchJson("/api/v1/conversations/{$conversation->id}/assign", [
            'assigned_user_id' => $salesRep->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_user_id', $salesRep->id);

        $this->assertEquals($salesRep->id, $conversation->fresh()->assigned_user_id);
    }

    public function test_can_mark_conversation_as_read(): void
    {
        Sanctum::actingAs($this->adminUser);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'unread_count' => 3,
        ]);

        $msg1 = Message::factory()->inbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'is_read' => false,
        ]);
        $msg2 = Message::factory()->inbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'is_read' => false,
        ]);

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);

        $conversation->refresh();
        $this->assertEquals(0, $conversation->unread_count);

        $this->assertTrue($msg1->fresh()->is_read);
        $this->assertTrue($msg2->fresh()->is_read);
    }

    public function test_can_retrieve_conversations_by_contact(): void
    {
        Sanctum::actingAs($this->adminUser);

        $contact = Contact::factory()->create();
        Conversation::factory()->count(2)->create(['contact_id' => $contact->id]);

        $otherContact = Contact::factory()->create();
        Conversation::factory()->count(3)->create(['contact_id' => $otherContact->id]);

        $response = $this->getJson("/api/v1/contacts/{$contact->id}/conversations");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthorized_user_cannot_manage_conversations(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $contact = Contact::factory()->create();

        $response = $this->postJson('/api/v1/conversations', [
            'contact_id' => $contact->id,
        ]);

        $response->assertStatus(403);
    }
}
