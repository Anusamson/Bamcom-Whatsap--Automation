<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\MessageDeliveryStatus;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsAppWebhookProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.verify_token', 'test_secure_verify_token_2026');
        Config::set('whatsapp.app_secret', 'test_app_secret_signature_key');

        $this->account = WhatsAppAccount::factory()->default()->create([
            'phone_number_id' => '109876543210',
            'display_phone_number' => '+2348000000000',
        ]);
    }

    /**
     * Helper to forge signed webhook requests.
     */
    protected function postSignedWebhook(array $payload)
    {
        $rawBody = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $rawBody, 'test_app_secret_signature_key');

        return $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/whatsapp/webhook', $payload);
    }

    public function test_incoming_text_message_auto_creates_contact_and_persists_message(): void
    {
        $messageId = 'wamid.HBgLMjM0ODAzOTg3NjU0MxUCMRIA';
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Chioma Adebayo'], 'wa_id' => '2348011223344'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348011223344',
                                        'id' => $messageId,
                                        'timestamp' => '1727000100',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Hello, I want to inquire about plots in Epe Waterfront.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertStatus(200)->assertJson(['status' => 'received']);

        // 1. Webhook event was persisted and marked processed (sync queue)
        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'meta_event_id' => $messageId,
            'status' => 'processed',
        ]);

        // 2. Contact was auto-created from Meta profile
        $contact = Contact::where('phone', '+2348011223344')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('Chioma', $contact->first_name);
        $this->assertEquals('Adebayo', $contact->last_name);
        $this->assertEquals(LeadSource::WhatsApp, $contact->lead_source);
        $this->assertEquals(ContactStatus::Lead, $contact->status);
        $this->assertNotNull($contact->last_contact_at);

        // 3. WhatsAppMessage was created
        $this->assertDatabaseHas('whatsapp_messages', [
            'contact_id' => $contact->id,
            'meta_message_id' => $messageId,
            'direction' => 'inbound',
            'sender_phone' => '2348011223344',
            'message_type' => 'text',
            'body' => 'Hello, I want to inquire about plots in Epe Waterfront.',
            'status' => 'received',
        ]);

        // 4. Conversation was created and connected to contact
        $this->assertDatabaseHas('conversations', [
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 1,
        ]);

        // 5. CRM Message was created
        $this->assertDatabaseHas('messages', [
            'contact_id' => $contact->id,
            'meta_message_id' => $messageId,
            'direction' => 'inbound',
            'body' => 'Hello, I want to inquire about plots in Epe Waterfront.',
            'delivery_status' => 'received',
            'is_read' => false,
        ]);

        // 6. Activity was logged
        $this->assertDatabaseHas('activities', [
            'activity_type' => 'whatsapp_message_received',
            'description' => 'Hello, I want to inquire about plots in Epe Waterfront.',
        ]);
    }

    public function test_incoming_message_links_to_existing_contact_and_updates_touchpoint(): void
    {
        $existingContact = Contact::factory()->create([
            'first_name' => 'Babatunde',
            'last_name' => 'Fashola',
            'phone' => '+2348099887766',
            'last_contact_at' => now()->subDays(5),
        ]);

        $messageId = 'wamid.HBgLMjM0ODA5OTg4Nzc2NhUCMRIA';
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Babatunde Fashola'], 'wa_id' => '2348099887766'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348099887766',
                                        'id' => $messageId,
                                        'timestamp' => '1727000200',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Are there 1,000 sqm commercial plots available?',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSignedWebhook($payload);
        $response->assertStatus(200);

        // No second contact created
        $this->assertEquals(1, Contact::where('phone', '+2348099887766')->count());

        $existingContact->refresh();
        $this->assertTrue($existingContact->last_contact_at->gt(now()->subMinute()));

        $this->assertDatabaseHas('whatsapp_messages', [
            'contact_id' => $existingContact->id,
            'meta_message_id' => $messageId,
            'body' => 'Are there 1,000 sqm commercial plots available?',
        ]);
    }

    public function test_duplicate_message_is_safely_ignored(): void
    {
        $messageId = 'wamid.HBgLMjM0ODAyMjMzNDQ1NQUCMRIA';
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Duplication Test'], 'wa_id' => '2348022334455'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348022334455',
                                        'id' => $messageId,
                                        'timestamp' => '1727000300',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Testing duplicate transmission',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Deliver first time
        $response1 = $this->postSignedWebhook($payload);
        $response1->assertStatus(200);

        $this->assertEquals(1, WhatsAppMessage::where('meta_message_id', $messageId)->count());
        $this->assertEquals(1, Activity::where('activity_type', 'whatsapp_message_received')->count());

        // Deliver identical second time (simulating Meta network retry)
        $response2 = $this->postSignedWebhook($payload);
        $response2->assertStatus(200);

        // Assert message and activity were NOT duplicated
        $this->assertEquals(1, WhatsAppMessage::where('meta_message_id', $messageId)->count());
        $this->assertEquals(1, Activity::where('activity_type', 'whatsapp_message_received')->count());
    }

    public function test_delivery_status_updates_delivered_and_read_timestamps(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348055667788']);
        $outboundMessageId = 'wamid.HBgLMjM0ODA1NTY2Nzc4OBUCMRIA';

        $message = WhatsAppMessage::create([
            'whatsapp_account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'meta_message_id' => $outboundMessageId,
            'direction' => 'outbound',
            'sender_phone' => '+2348000000000',
            'recipient_phone' => '+2348055667788',
            'message_type' => 'text',
            'body' => 'Your inspection for Epe Waterfront is confirmed.',
            'status' => 'sent',
            'sent_at' => now()->subMinutes(2),
        ]);

        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);
        $crmMessage = Message::create([
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'meta_message_id' => $outboundMessageId,
            'direction' => 'outbound',
            'delivery_status' => MessageDeliveryStatus::Sent,
            'body' => 'Your inspection for Epe Waterfront is confirmed.',
        ]);

        // 1. Webhook delivers 'delivered' receipt
        $deliveredPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'statuses' => [
                                    [
                                        'id' => $outboundMessageId,
                                        'status' => 'delivered',
                                        'timestamp' => '1727000400',
                                        'recipient_id' => '2348055667788',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($deliveredPayload)->assertStatus(200);

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);

        $crmMessage->refresh();
        $this->assertEquals(MessageDeliveryStatus::Delivered, $crmMessage->delivery_status);
        $this->assertNotNull($crmMessage->delivered_at);

        // 2. Webhook delivers 'read' receipt
        $readPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'statuses' => [
                                    [
                                        'id' => $outboundMessageId,
                                        'status' => 'read',
                                        'timestamp' => '1727000450',
                                        'recipient_id' => '2348055667788',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($readPayload)->assertStatus(200);

        $message->refresh();
        $this->assertEquals('read', $message->status);
        $this->assertNotNull($message->read_at);

        $crmMessage->refresh();
        $this->assertEquals(MessageDeliveryStatus::Read, $crmMessage->delivery_status);
        $this->assertTrue($crmMessage->is_read);
        $this->assertNotNull($crmMessage->read_at);
    }

    public function test_delivery_status_updates_failed_status_with_error(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348055667799']);
        $outboundMessageId = 'wamid.HBgLMjM0ODA1NTY2Nzc5OBUCMRIA';

        $message = WhatsAppMessage::create([
            'whatsapp_account_id' => $this->account->id,
            'contact_id' => $contact->id,
            'meta_message_id' => $outboundMessageId,
            'direction' => 'outbound',
            'sender_phone' => '+2348000000000',
            'recipient_phone' => '+2348055667799',
            'message_type' => 'text',
            'body' => 'Checking payment receipt',
            'status' => 'sent',
        ]);

        $failedPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'statuses' => [
                                    [
                                        'id' => $outboundMessageId,
                                        'status' => 'failed',
                                        'timestamp' => '1727000500',
                                        'recipient_id' => '2348055667799',
                                        'errors' => [
                                            [
                                                'code' => 131026,
                                                'title' => 'Message undeliverable to recipient',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($failedPayload)->assertStatus(200);

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertEquals('Message undeliverable to recipient', $message->error_message);
    }

    public function test_incoming_image_and_interactive_messages_parsed_correctly(): void
    {
        // 1. Incoming Image
        $imageMessageId = 'wamid.HBgLMjM0ODAxMTIyMzM0NBUCMRIA';
        $imagePayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Folake Bankole'], 'wa_id' => '2348077665544'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348077665544',
                                        'id' => $imageMessageId,
                                        'timestamp' => '1727000600',
                                        'type' => 'image',
                                        'image' => [
                                            'caption' => 'Proof of initial deposit transfer',
                                            'mime_type' => 'image/jpeg',
                                            'sha256' => 'abc123sha256',
                                            'id' => 'media_id_998877',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($imagePayload)->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => $imageMessageId,
            'message_type' => 'image',
            'body' => 'Proof of initial deposit transfer',
            'media_url' => 'media_id_998877',
            'media_mime_type' => 'image/jpeg',
        ]);

        // 2. Interactive Button Reply
        $interactiveMessageId = 'wamid.HBgLMjM0ODAxMTIyMzM0NlUCMRIA';
        $interactivePayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '+2348000000000',
                                    'phone_number_id' => '109876543210',
                                ],
                                'contacts' => [
                                    ['profile' => ['name' => 'Folake Bankole'], 'wa_id' => '2348077665544'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348077665544',
                                        'id' => $interactiveMessageId,
                                        'timestamp' => '1727000650',
                                        'type' => 'interactive',
                                        'interactive' => [
                                            'type' => 'button_reply',
                                            'button_reply' => [
                                                'id' => 'btn_confirm_inspection',
                                                'title' => 'Confirm Inspection Saturday 10am',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postSignedWebhook($interactivePayload)->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => $interactiveMessageId,
            'message_type' => 'interactive',
            'body' => 'Confirm Inspection Saturday 10am',
        ]);
    }

    public function test_processing_failure_is_recorded_on_event(): void
    {
        $event = WhatsAppWebhookEvent::create([
            'whatsapp_account_id' => $this->account->id,
            'event_type' => 'messages',
            'payload' => [
                'entry' => 'invalid_entry_structure_that_causes_type_error',
            ],
            'status' => 'pending',
        ]);

        try {
            $job = new ProcessWhatsAppWebhook($event);
            $job->handle();
        } catch (\Throwable $e) {
            // Expected failure
        }

        $event->refresh();
        $this->assertEquals('failed', $event->status);
        $this->assertNotNull($event->error_message);
    }
}
