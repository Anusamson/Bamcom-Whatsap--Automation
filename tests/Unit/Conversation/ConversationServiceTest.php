<?php

namespace Tests\Unit\Conversation;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\MessageDeliveryStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Conversation\ConversationService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ConversationService $service;

    protected WhatsAppMessageService $messageServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageServiceMock = Mockery::mock(WhatsAppMessageService::class);
        $this->service = new ConversationService($this->messageServiceMock);
    }

    public function test_find_or_create_active_conversation_finds_existing_open_conversation(): void
    {
        $contact = Contact::factory()->create();
        $existing = Conversation::factory()->open()->create(['contact_id' => $contact->id]);

        $resolved = $this->service->findOrCreateActiveConversation($contact);

        $this->assertEquals($existing->id, $resolved->id);
        $this->assertEquals(1, Conversation::where('contact_id', $contact->id)->count());
    }

    public function test_find_or_create_active_conversation_reopens_pending_conversation(): void
    {
        $contact = Contact::factory()->create();
        $pending = Conversation::factory()->pending()->create(['contact_id' => $contact->id]);

        $resolved = $this->service->findOrCreateActiveConversation($contact);

        $this->assertEquals($pending->id, $resolved->id);
        $this->assertEquals(ConversationStatus::Open, $resolved->fresh()->status);
    }

    public function test_find_or_create_active_conversation_creates_new_when_only_closed_exist(): void
    {
        $contact = Contact::factory()->create();
        Conversation::factory()->closed()->create(['contact_id' => $contact->id]);

        $newConv = $this->service->findOrCreateActiveConversation($contact);

        $this->assertEquals(ConversationStatus::Open, $newConv->status);
        $this->assertEquals(2, Conversation::where('contact_id', $contact->id)->count());
    }

    public function test_update_mode_and_status(): void
    {
        $conversation = Conversation::factory()->ai()->open()->create();

        $this->service->updateMode($conversation, ConversationMode::Human);
        $this->assertEquals(ConversationMode::Human, $conversation->fresh()->mode);

        $this->service->updateStatus($conversation, ConversationStatus::Closed);
        $this->assertEquals(ConversationStatus::Closed, $conversation->fresh()->status);
        $this->assertNotNull($conversation->fresh()->closed_at);

        $this->service->updateStatus($conversation, ConversationStatus::Open);
        $this->assertEquals(ConversationStatus::Open, $conversation->fresh()->status);
        $this->assertNull($conversation->fresh()->closed_at);
    }

    public function test_assign_user(): void
    {
        $conversation = Conversation::factory()->create(['assigned_user_id' => null]);
        $user = User::factory()->create();

        $this->service->assignUser($conversation, $user);
        $this->assertEquals($user->id, $conversation->fresh()->assigned_user_id);

        $this->service->assignUser($conversation, null);
        $this->assertNull($conversation->fresh()->assigned_user_id);
    }

    public function test_mark_conversation_read_resets_unread_and_updates_messages(): void
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'contact_id' => $contact->id,
            'unread_count' => 2,
        ]);

        $msg1 = Message::factory()->inbound()->create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'is_read' => false,
            'delivery_status' => MessageDeliveryStatus::Received,
        ]);

        $this->service->markConversationRead($conversation);

        $this->assertEquals(0, $conversation->fresh()->unread_count);
        $this->assertTrue($msg1->fresh()->is_read);
        $this->assertEquals(MessageDeliveryStatus::Read, $msg1->fresh()->delivery_status);
        $this->assertNotNull($msg1->fresh()->read_at);
    }
}
