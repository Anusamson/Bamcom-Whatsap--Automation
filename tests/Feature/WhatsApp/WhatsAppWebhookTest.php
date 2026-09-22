<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.verify_token', 'test_secure_verify_token_2026');
        Config::set('whatsapp.app_secret', 'test_app_secret_signature_key');
    }

    public function test_webhook_get_verification_handshake_succeeds_with_valid_token(): void
    {
        $account = WhatsAppAccount::factory()->default()->create([
            'webhook_verified_at' => null,
        ]);

        $response = $this->get('/api/v1/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=test_secure_verify_token_2026&hub_challenge=987654321');

        $response->assertStatus(200);
        $this->assertEquals('987654321', $response->getContent());

        $account->refresh();
        $this->assertNotNull($account->webhook_verified_at);
    }

    public function test_webhook_get_verification_handshake_fails_with_invalid_token(): void
    {
        $response = $this->get('/api/v1/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong_token&hub_challenge=987654321');

        $response->assertStatus(403);
    }

    public function test_webhook_post_receives_and_records_message_event(): void
    {
        Queue::fake();

        $account = WhatsAppAccount::factory()->create([
            'phone_number_id' => '109876543210',
        ]);

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
                                    ['profile' => ['name' => 'Emeka Obi'], 'wa_id' => '2348039876543'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '2348039876543',
                                        'id' => 'wamid.HBgLMjM0ODAzOTg3NjU0MwUCMRIA',
                                        'timestamp' => '1727000000',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'I would like to schedule an inspection for Epe Waterfront.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $rawBody, 'test_app_secret_signature_key');

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'received']);

        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'whatsapp_account_id' => $account->id,
            'event_type' => 'messages',
            'sender_phone' => '2348039876543',
            'status' => 'pending',
        ]);

        $event = WhatsAppWebhookEvent::latest('id')->first();
        $this->assertNotNull($event);
        $this->assertEquals('wamid.HBgLMjM0ODAzOTg3NjU0MwUCMRIA', $event->meta_event_id);

        Queue::assertPushed(ProcessWhatsAppWebhook::class, function ($job) use ($event) {
            return $job->event->id === $event->id;
        });
    }

    public function test_webhook_post_records_failed_status_when_signature_is_invalid(): void
    {
        Queue::fake();

        $payload = ['object' => 'whatsapp_business_account', 'entry' => []];

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256=invalid_tampered_signature_hash',
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/whatsapp/webhook', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('whatsapp_webhook_events', [
            'status' => 'failed',
            'error_message' => 'Invalid HMAC-SHA256 signature',
        ]);

        Queue::assertNotPushed(ProcessWhatsAppWebhook::class);
    }
}
