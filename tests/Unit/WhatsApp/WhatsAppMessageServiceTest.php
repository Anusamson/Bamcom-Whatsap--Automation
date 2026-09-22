<?php

namespace Tests\Unit\WhatsApp;

use App\Models\Contact;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppMessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.base_url', 'https://graph.facebook.com');
        Config::set('whatsapp.api_version', 'v21.0');
        Config::set('whatsapp.access_token', 'mock_token');
        Config::set('whatsapp.phone_number_id', '109876543210');

        $client = new WhatsAppClient;
        $this->messageService = new WhatsAppMessageService($client);
    }

    public function test_phone_number_normalization(): void
    {
        // 11 digits Nigerian format starting with 0
        $this->assertEquals('2348031234567', $this->messageService->normalizePhone('08031234567'));
        $this->assertEquals('2348031234567', $this->messageService->normalizePhone('0803-123-4567'));

        // International format with +
        $this->assertEquals('2348031234567', $this->messageService->normalizePhone('+2348031234567'));

        // 10 digits without leading zero
        $this->assertEquals('2348031234567', $this->messageService->normalizePhone('8031234567'));

        // Already normalized format
        $this->assertEquals('2348031234567', $this->messageService->normalizePhone('2348031234567'));
    }

    public function test_send_text_message_updates_contact_touchpoint_and_creates_activity(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messages' => [['id' => 'wamid.HBgLMjM0ODAzMTIzNDU2NxUCMRIA']],
            ], 200),
        ]);

        $contact = Contact::factory()->create([
            'phone' => '+2348031234567',
            'last_contact_at' => null,
        ]);
        $user = User::factory()->create();

        $result = $this->messageService->sendTextMessage(
            $contact->phone,
            'Hello Mr. Adeleke, thank you for your interest in our Lekki Phase 1 duplex.',
            $contact,
            $user
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.HBgLMjM0ODAzMTIzNDU2NxUCMRIA', $result['message_id']);

        $contact->refresh();
        $this->assertNotNull($contact->last_contact_at);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'activity_type' => 'whatsapp_text_sent',
        ]);
    }

    public function test_send_template_message_formats_components_and_creates_activity(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'messages' => [['id' => 'wamid.HBgLMjM0ODAzMTIzNDU2NxUCMRIB']],
            ], 200),
        ]);

        $contact = Contact::factory()->create(['phone' => '+2348031234567']);
        $user = User::factory()->create();

        $result = $this->messageService->sendTemplateMessage(
            $contact->phone,
            'inspection_booking_confirmation',
            ['Adewale', 'Epe Waterfront Plots', 'Saturday at 10 AM', 'Segun Adebayo'],
            'en_US',
            $contact,
            $user
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('wamid.HBgLMjM0ODAzMTIzNDU2NxUCMRIB', $result['message_id']);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'activity_type' => 'whatsapp_template_sent',
        ]);
    }

    public function test_send_message_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210/messages' => Http::response([
                'error' => [
                    'message' => 'Recipient phone number not registered on WhatsApp.',
                    'code' => 131026,
                ],
            ], 400),
        ]);

        $result = $this->messageService->sendTextMessage('08099999999', 'Hello test');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Recipient phone number not registered', $result['error']);
    }
}
