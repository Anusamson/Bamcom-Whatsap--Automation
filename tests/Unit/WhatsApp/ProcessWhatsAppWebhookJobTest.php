<?php

namespace Tests\Unit\WhatsApp;

use App\Jobs\ProcessIncomingMessage;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\Contact;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessWhatsAppWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_dispatches_process_incoming_message_for_each_message(): void
    {
        Queue::fake();

        $account = WhatsAppAccount::factory()->create([
            'phone_number_id' => '1234567890',
        ]);

        $event = WhatsAppWebhookEvent::create([
            'whatsapp_account_id' => $account->id,
            'event_type' => 'messages',
            'payload' => [
                'entry' => [
                    [
                        'id' => 'WABA_123',
                        'changes' => [
                            [
                                'value' => [
                                    'messaging_product' => 'whatsapp',
                                    'metadata' => [
                                        'display_phone_number' => '+2348000000000',
                                        'phone_number_id' => '1234567890',
                                    ],
                                    'contacts' => [
                                        ['profile' => ['name' => 'Alice Doe'], 'wa_id' => '2348011111111'],
                                    ],
                                    'messages' => [
                                        [
                                            'from' => '2348011111111',
                                            'id' => 'wamid.MSG1',
                                            'type' => 'text',
                                            'text' => ['body' => 'Message 1'],
                                        ],
                                        [
                                            'from' => '2348011111111',
                                            'id' => 'wamid.MSG2',
                                            'type' => 'text',
                                            'text' => ['body' => 'Message 2'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        $job = new ProcessWhatsAppWebhook($event);
        $job->handle();

        Queue::assertPushed(ProcessIncomingMessage::class, 2);

        $event->refresh();
        $this->assertEquals('processed', $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_handle_updates_message_status_receipts(): void
    {
        $contact = Contact::factory()->create(['phone' => '+2348012345678']);
        $message = WhatsAppMessage::create([
            'contact_id' => $contact->id,
            'meta_message_id' => 'wamid.DELIV_TEST',
            'direction' => 'outbound',
            'sender_phone' => '+2348000000000',
            'recipient_phone' => '+2348012345678',
            'message_type' => 'text',
            'body' => 'Test delivery update',
            'status' => 'sent',
        ]);

        $event = WhatsAppWebhookEvent::create([
            'event_type' => 'messages',
            'payload' => [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'statuses' => [
                                        [
                                            'id' => 'wamid.DELIV_TEST',
                                            'status' => 'delivered',
                                            'timestamp' => '1727001000',
                                            'recipient_id' => '2348012345678',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        $job = new ProcessWhatsAppWebhook($event);
        $job->handle();

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_handle_skips_when_event_already_processed(): void
    {
        Queue::fake();

        $event = WhatsAppWebhookEvent::create([
            'event_type' => 'messages',
            'payload' => [
                'entry' => [
                    [
                        'changes' => [
                            [
                                'value' => [
                                    'messages' => [
                                        ['from' => '2348011111111', 'id' => 'wamid.SKIP', 'type' => 'text'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'processed',
        ]);

        $job = new ProcessWhatsAppWebhook($event);
        $job->handle();

        Queue::assertNothingPushed();
    }
}
