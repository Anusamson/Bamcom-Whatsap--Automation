<?php

namespace Tests\Unit\WhatsApp;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessIncomingMessageJobTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppAccount $account;

    protected WhatsAppWebhookEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = WhatsAppAccount::factory()->default()->create([
            'phone_number_id' => '1234567890',
            'display_phone_number' => '+2348000000000',
        ]);

        $this->event = WhatsAppWebhookEvent::create([
            'whatsapp_account_id' => $this->account->id,
            'event_type' => 'messages',
            'payload' => [],
            'status' => 'pending',
        ]);
    }

    public function test_skips_when_meta_message_id_already_exists(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348011223344']);
        WhatsAppMessage::create([
            'contact_id' => $contact->id,
            'meta_message_id' => 'wamid.EXISTING_ID',
            'direction' => 'inbound',
            'sender_phone' => '2348011223344',
            'recipient_phone' => '+2348000000000',
            'message_type' => 'text',
            'body' => 'Initial message',
            'status' => 'received',
        ]);

        $messageData = [
            'id' => 'wamid.EXISTING_ID',
            'from' => '2348011223344',
            'type' => 'text',
            'text' => ['body' => 'Duplicate message'],
        ];

        $job = new ProcessIncomingMessage($this->event, $messageData);
        $job->handle();

        // Ensure no new message or activity was created
        $this->assertEquals(1, WhatsAppMessage::where('meta_message_id', 'wamid.EXISTING_ID')->count());
        $this->assertEquals(0, Activity::count());
    }

    public function test_creates_contact_and_message_for_new_sender(): void
    {
        $messageData = [
            'id' => 'wamid.NEW_SENDER_1',
            'from' => '2348099112233',
            'timestamp' => '1727002000',
            'type' => 'text',
            'text' => ['body' => 'Price inquiry for Lekki properties'],
        ];
        $contactData = [
            'profile' => ['name' => 'Ngozi Eze'],
            'wa_id' => '2348099112233',
        ];

        $job = new ProcessIncomingMessage($this->event, $messageData, $contactData);
        $job->handle();

        $contact = Contact::where('phone', '+2348099112233')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Ngozi', $contact->first_name);
        $this->assertEquals('Eze', $contact->last_name);

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => 'wamid.NEW_SENDER_1',
            'contact_id' => $contact->id,
            'body' => 'Price inquiry for Lekki properties',
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('activities', [
            'activity_type' => 'whatsapp_message_received',
            'description' => 'Price inquiry for Lekki properties',
        ]);
    }

    public function test_links_message_to_existing_contact_and_touches_timestamp(): void
    {
        $contact = Contact::factory()->create([
            'phone' => '+2348123456789',
            'last_contact_at' => now()->subDays(3),
        ]);

        $messageData = [
            'id' => 'wamid.EXISTING_CONTACT_MSG',
            'from' => '2348123456789',
            'type' => 'text',
            'text' => ['body' => 'Hello again'],
        ];

        $job = new ProcessIncomingMessage($this->event, $messageData);
        $job->handle();

        $this->assertEquals(1, Contact::where('phone', '+2348123456789')->count());

        $contact->refresh();
        $this->assertTrue($contact->last_contact_at->gt(now()->subMinute()));

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => 'wamid.EXISTING_CONTACT_MSG',
            'contact_id' => $contact->id,
        ]);
    }

    public function test_parses_document_and_reaction_types(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348033334444']);

        // 1. Document message
        $docData = [
            'id' => 'wamid.DOC_1',
            'from' => '2348033334444',
            'type' => 'document',
            'document' => [
                'filename' => 'bank_statement.pdf',
                'caption' => 'Income proof',
                'mime_type' => 'application/pdf',
                'id' => 'media_doc_123',
            ],
        ];

        $job = new ProcessIncomingMessage($this->event, $docData);
        $job->handle();

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => 'wamid.DOC_1',
            'message_type' => 'document',
            'body' => 'Income proof',
            'media_url' => 'media_doc_123',
            'media_mime_type' => 'application/pdf',
        ]);

        // 2. Reaction message
        $reactionData = [
            'id' => 'wamid.REACT_1',
            'from' => '2348033334444',
            'type' => 'reaction',
            'reaction' => [
                'message_id' => 'wamid.DOC_1',
                'emoji' => '👍',
            ],
        ];

        $job2 = new ProcessIncomingMessage($this->event, $reactionData);
        $job2->handle();

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => 'wamid.REACT_1',
            'message_type' => 'reaction',
            'body' => '👍',
        ]);
    }
}
