<?php

namespace Tests\Unit\WhatsApp;

use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('whatsapp.base_url', 'https://graph.facebook.com');
        Config::set('whatsapp.api_version', 'v21.0');
        Config::set('whatsapp.access_token', 'mock_token');
        Config::set('whatsapp.business_account_id', '987654321098');

        $client = new WhatsAppClient;
        $this->templateService = new TemplateService($client);
    }

    public function test_sync_templates_from_meta_upserts_into_database(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/987654321098/message_templates*' => Http::response([
                'data' => [
                    [
                        'id' => '10192837465',
                        'name' => 'inspection_booked_notice',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'HEADER',
                                'format' => 'TEXT',
                                'text' => 'Inspection Notice',
                            ],
                            [
                                'type' => 'BODY',
                                'text' => 'Dear {{1}}, your site inspection for {{2}} is confirmed for {{3}}.',
                            ],
                            [
                                'type' => 'FOOTER',
                                'text' => 'Bamcom Real Estate Ltd',
                            ],
                            [
                                'type' => 'BUTTONS',
                                'buttons' => [
                                    ['type' => 'QUICK_REPLY', 'text' => 'Confirm'],
                                    ['type' => 'QUICK_REPLY', 'text' => 'Cancel'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $account = WhatsAppAccount::factory()->create(['waba_id' => '987654321098']);

        $result = $this->templateService->syncTemplatesFromMeta($account);

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals('inspection_booked_notice', $result['templates'][0]);

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'inspection_booked_notice',
            'meta_template_id' => '10192837465',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => WhatsAppTemplateStatus::Approved->value,
            'header_type' => 'TEXT',
            'header_content' => 'Inspection Notice',
            'footer_text' => 'Bamcom Real Estate Ltd',
        ]);

        $template = WhatsAppTemplate::where('name', 'inspection_booked_notice')->first();
        $this->assertNotNull($template);
        $this->assertCount(2, $template->buttons);
    }

    public function test_template_rendering_replaces_placeholders(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'body_text' => 'Hello {{1}}, welcome to {{2}} at {{3}}.',
        ]);

        $rendered = $this->templateService->renderPreview($template, ['Tunde', 'Lekki Phase 1 Luxury Duplex', '₦180,000,000']);

        $this->assertEquals('Hello Tunde, welcome to Lekki Phase 1 Luxury Duplex at ₦180,000,000.', $rendered);
    }

    public function test_get_templates_filters_by_category_and_search(): void
    {
        WhatsAppTemplate::factory()->create([
            'name' => 'marketing_promo_bonanza',
            'category' => 'MARKETING',
            'body_text' => 'Special promo discount on all Epe plots.',
        ]);
        WhatsAppTemplate::factory()->create([
            'name' => 'payment_reminder_utility',
            'category' => 'UTILITY',
            'body_text' => 'Installment reminder for your plot.',
        ]);

        $marketingTemplates = $this->templateService->getTemplates(['category' => 'MARKETING']);
        $this->assertCount(1, $marketingTemplates);
        $this->assertEquals('marketing_promo_bonanza', $marketingTemplates->first()->name);

        $searchResults = $this->templateService->getTemplates(['search' => 'Epe']);
        $this->assertCount(1, $searchResults);
        $this->assertEquals('marketing_promo_bonanza', $searchResults->first()->name);
    }
}
