<?php

namespace Database\Seeders;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WhatsAppSeeder extends Seeder
{
    /**
     * Seed official WhatsApp Business Platform default account & standard real estate templates.
     */
    public function run(): void
    {
        $account = WhatsAppAccount::firstOrCreate(
            ['phone_number_id' => '109876543210123'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Bamcom Primary WhatsApp Line',
                'waba_id' => '123456789098765',
                'display_phone_number' => '+234 814 000 0001',
                'verified_name' => 'Bamcom Real Estate Ltd',
                'quality_rating' => 'GREEN',
                'status' => WhatsAppAccountStatus::Connected,
                'is_default' => true,
                'webhook_verified_at' => now(),
                'last_synced_at' => now(),
                'metadata' => [
                    'vertical' => 'REAL_ESTATE',
                    'timezone' => 'Africa/Lagos',
                ],
            ]
        );

        $templates = [
            [
                'name' => 'inspection_booking_confirmation',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => WhatsAppTemplateStatus::Approved,
                'header_type' => 'TEXT',
                'header_content' => 'Site Inspection Confirmation',
                'body_text' => 'Hello {{1}}, your site inspection for {{2}} with Bamcom Real Estate is confirmed for {{3}}. Our assigned representative {{4}} will meet you on site.',
                'footer_text' => 'Bamcom Real Estate - Safe Land & Luxury Living',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Confirm Attendance'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Reschedule Inspection'],
                ],
            ],
            [
                'name' => 'property_brochure_delivery',
                'language' => 'en_US',
                'category' => 'MARKETING',
                'status' => WhatsAppTemplateStatus::Approved,
                'header_type' => 'DOCUMENT',
                'header_content' => 'Official Brochure',
                'body_text' => 'Hi {{1}}, here is the complete project brochure, layout masterplan, and verified title document overview for {{2}} in {{3}}.',
                'footer_text' => 'Bamcom Properties - Building Tomorrow',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'View Video Tour', 'url' => 'https://bamcom.ng/tour'],
                ],
            ],
            [
                'name' => 'payment_milestone_reminder',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => WhatsAppTemplateStatus::Approved,
                'header_type' => 'TEXT',
                'header_content' => 'Payment Milestone Notice',
                'body_text' => 'Dear {{1}}, this is a friendly reminder that your scheduled milestone payment of ₦{{2}} for {{3}} is due on {{4}}. Thank you for building with Bamcom.',
                'footer_text' => 'Finance & Accounts Desk',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Payment Completed'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Contact Account Officer'],
                ],
            ],
        ];

        foreach ($templates as $data) {
            WhatsAppTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language' => $data['language'],
                ],
                array_merge($data, [
                    'uuid' => (string) Str::uuid(),
                    'whatsapp_account_id' => $account->id,
                    'meta_template_id' => (string) rand(1000000000, 9999999999),
                    'last_synced_at' => now(),
                ])
            );
        }
    }
}
