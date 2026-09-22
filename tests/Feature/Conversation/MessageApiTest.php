<?php

namespace Tests\Feature\Conversation;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MessageApiTest extends TestCase
{
    use RefreshDatabase;

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

        $this->agentUser = User::factory()->create([
            'role' => UserRole::SalesManager,
        ]);
        $this->agentUser->givePermissionTo([
            PermissionEnum::ConversationsView->value,
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

    public function test_can_list_messages_within_conversation(): void
    {
        Sanctum::actingAs($this->agentUser);

        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        Message::factory()->count(5)->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->getJson("/api/v1/conversations/{$conversation->id}/messages");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'conversation_id',
                        'direction',
                        'type',
                        'body',
                        'delivery_status',
                    ],
                ],
                'meta',
                'links',
            ]);
    }

    public function test_can_send_outbound_text_message_to_contact_in_conversation(): void
    {
        Sanctum::actingAs($this->agentUser);

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
            'last_message_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Good day, we have reserved plot 14 for your inspection this weekend.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.body', 'Good day, we have reserved plot 14 for your inspection this weekend.')
            ->assertJsonPath('data.delivery_status', 'sent')
            ->assertJsonPath('data.meta_message_id', 'wamid.HBgLMjM0ODAxMjM0NTY3OBUCMRIA');

        // Check authoritative Message record was persisted
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'meta_message_id' => 'wamid.HBgLMjM0ODAxMjM0NTY3OBUCMRIA',
            'direction' => 'outbound',
            'delivery_status' => 'sent',
            'body' => 'Good day, we have reserved plot 14 for your inspection this weekend.',
        ]);

        // Check conversation last_message_at was touched
        $conversation->refresh();
        $this->assertTrue($conversation->last_message_at->gt(now()->subMinute()));
    }

    public function test_can_send_outbound_template_message(): void
    {
        Sanctum::actingAs($this->agentUser);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348012345678', 'wa_id' => '2348012345678']],
                'messages' => [['id' => 'wamid.TEMPLATE_MSG_123']],
            ], 200),
        ]);

        $contact = Contact::factory()->create(['phone' => '+2348012345678']);
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'type' => 'template',
            'template_name' => 'inspection_booking_confirmation',
            'template_parameters' => ['Epe Waterfront', 'Saturday 10am'],
            'body' => 'Inspection booking confirmed for Epe Waterfront on Saturday 10am',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'template')
            ->assertJsonPath('data.meta_message_id', 'wamid.TEMPLATE_MSG_123');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'template',
            'meta_message_id' => 'wamid.TEMPLATE_MSG_123',
        ]);
    }

    public function test_unauthorized_user_cannot_send_messages(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $conversation = Conversation::factory()->create();

        $response = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
            'body' => 'Should fail',
        ]);

        $response->assertStatus(403);
    }
}
