<?php

namespace Tests\Unit\WhatsApp;

use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppClientTest extends TestCase
{
    protected WhatsAppClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.base_url', 'https://graph.facebook.com');
        Config::set('whatsapp.api_version', 'v21.0');
        Config::set('whatsapp.access_token', 'mock_access_token_xyz');
        Config::set('whatsapp.phone_number_id', '109876543210');
        Config::set('whatsapp.business_account_id', '987654321098');
        Config::set('whatsapp.app_secret', 'mock_app_secret_123');
        Config::set('whatsapp.verify_token', 'test_verify_token_456');

        $this->client = new WhatsAppClient;
    }

    public function test_client_identifies_configured_state(): void
    {
        $this->assertTrue($this->client->isConfigured());

        Config::set('whatsapp.access_token', '');
        $unconfiguredClient = new WhatsAppClient;
        $this->assertFalse($unconfiguredClient->isConfigured());
    }

    public function test_send_text_message_formats_correct_payload(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348012345678', 'wa_id' => '2348012345678']],
                'messages' => [['id' => 'wamid.mock_text_msg_001']],
            ], 200),
        ]);

        $response = $this->client->sendTextMessage('2348012345678', 'Welcome to Bamcom Real Estate!');

        $this->assertEquals('wamid.mock_text_msg_001', $response['messages'][0]['id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://graph.facebook.com/v21.0/109876543210/messages'
                && $request->hasHeader('Authorization', 'Bearer mock_access_token_xyz')
                && $data['messaging_product'] === 'whatsapp'
                && $data['to'] === '2348012345678'
                && $data['type'] === 'text'
                && $data['text']['body'] === 'Welcome to Bamcom Real Estate!';
        });
    }

    public function test_send_template_message_formats_correct_payload(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '2348012345678', 'wa_id' => '2348012345678']],
                'messages' => [['id' => 'wamid.mock_template_msg_002']],
            ], 200),
        ]);

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'Babatunde'],
                    ['type' => 'text', 'text' => 'Epe Waterfront'],
                ],
            ],
        ];

        $response = $this->client->sendTemplateMessage(
            '2348012345678',
            'inspection_booking_confirmation',
            'en_US',
            $components
        );

        $this->assertEquals('wamid.mock_template_msg_002', $response['messages'][0]['id']);

        Http::assertSent(function ($request) use ($components) {
            $data = $request->data();

            return $data['type'] === 'template'
                && $data['template']['name'] === 'inspection_booking_confirmation'
                && $data['template']['language']['code'] === 'en_US'
                && $data['template']['components'] === $components;
        });
    }

    public function test_send_media_message_formats_correct_payload(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.mock_doc_msg_003']],
            ], 200),
        ]);

        $response = $this->client->sendMediaMessage(
            '2348012345678',
            'document',
            'https://bamcom.ng/brochures/epe_waterfront.pdf',
            'Official Estate Layout Brochure'
        );

        $this->assertEquals('wamid.mock_doc_msg_003', $response['messages'][0]['id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $data['type'] === 'document'
                && $data['document']['link'] === 'https://bamcom.ng/brochures/epe_waterfront.pdf'
                && $data['document']['caption'] === 'Official Estate Layout Brochure';
        });
    }

    public function test_fetch_templates_requests_waba_endpoint(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/987654321098/message_templates*' => Http::response([
                'data' => [
                    [
                        'id' => '123456',
                        'name' => 'inspection_alert',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'en_US',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->client->fetchTemplates();

        $this->assertCount(1, $response['data']);
        $this->assertEquals('inspection_alert', $response['data'][0]['name']);
    }

    public function test_test_connection_success(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210*' => Http::response([
                'verified_name' => 'Bamcom Real Estate Ltd',
                'display_phone_number' => '+234 814 000 0001',
                'quality_rating' => 'GREEN',
                'code_verification_status' => 'VERIFIED',
            ], 200),
        ]);

        $result = $this->client->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('connected', $result['status']);
        $this->assertEquals('Bamcom Real Estate Ltd', $result['data']['verified_name']);
        $this->assertEquals('GREEN', $result['data']['quality_rating']);
    }

    public function test_test_connection_failure(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210*' => Http::response([
                'error' => [
                    'message' => 'Invalid OAuth access token.',
                    'code' => 190,
                ],
            ], 401),
        ]);

        $result = $this->client->testConnection();

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('Invalid OAuth access token', $result['error']);
    }

    public function test_verify_webhook_signature(): void
    {
        $payload = json_encode(['test' => 'webhook_data']);
        $secret = 'mock_app_secret_123';
        $validSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);
        $invalidSignature = 'sha256='.hash_hmac('sha256', $payload, 'wrong_secret');

        $this->assertTrue($this->client->verifyWebhookSignature($payload, $validSignature));
        $this->assertFalse($this->client->verifyWebhookSignature($payload, $invalidSignature));
        $this->assertFalse($this->client->verifyWebhookSignature($payload, 'invalid_header_format'));
        $this->assertFalse($this->client->verifyWebhookSignature($payload, null));
    }

    public function test_verify_webhook_token(): void
    {
        $this->assertTrue($this->client->verifyWebhookToken('test_verify_token_456'));
        $this->assertFalse($this->client->verifyWebhookToken('incorrect_token'));
        $this->assertFalse($this->client->verifyWebhookToken(null));
    }
}
