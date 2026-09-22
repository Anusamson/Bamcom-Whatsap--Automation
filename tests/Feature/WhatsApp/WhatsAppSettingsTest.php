<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\PermissionEnum;
use App\Enums\UserRole;
use App\Enums\WhatsAppAccountStatus;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->adminUser->assignRole($superAdminRole);

        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::CustomerSupport]);

        Config::set('whatsapp.base_url', 'https://graph.facebook.com');
        Config::set('whatsapp.api_version', 'v21.0');
        Config::set('whatsapp.access_token', 'EAAG_SECRET_TOKEN_DO_NOT_EXPOSE_123456');
        Config::set('whatsapp.phone_number_id', '109876543210');
        Config::set('whatsapp.business_account_id', '987654321098');
        Config::set('whatsapp.app_secret', 'SECRET_APP_KEY_789');
    }

    public function test_unauthenticated_user_cannot_access_whatsapp_settings(): void
    {
        $response = $this->get(route('whatsapp.settings.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_whatsapp_settings(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('whatsapp.settings.index'));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_settings_without_exposing_secret_tokens(): void
    {
        WhatsAppAccount::factory()->create([
            'phone_number_id' => '109876543210',
            'verified_name' => 'Bamcom Real Estate Ltd',
        ]);
        WhatsAppTemplate::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)->get(route('whatsapp.settings.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/WhatsApp')
                ->has('settings')
                ->where('settings.is_configured', true)
                ->where('settings.phone_number_id', '109876543210')
                ->where('settings.business_account_id', '987654321098')
                ->where('settings.api_version', 'v21.0')
                ->whereNot('settings.masked_access_token', 'EAAG_SECRET_TOKEN_DO_NOT_EXPOSE_123456')
                ->has('accounts', 1)
                ->has('templates', 3)
                ->has('webhookStats')
            );

        // Explicit security check: Ensure raw secret token is NOT leaked in full response HTML/JSON payload
        $this->assertStringNotContainsString('EAAG_SECRET_TOKEN_DO_NOT_EXPOSE_123456', $response->getContent());
        $this->assertStringNotContainsString('SECRET_APP_KEY_789', $response->getContent());
    }

    public function test_test_connection_endpoint_updates_account_with_meta_details(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/109876543210*' => Http::response([
                'verified_name' => 'Bamcom Real Estate Ltd',
                'display_phone_number' => '+234 814 000 0001',
                'quality_rating' => 'GREEN',
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->from(route('whatsapp.settings.index'))
            ->post(route('whatsapp.settings.test-connection'));

        $response->assertRedirect(route('whatsapp.settings.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_accounts', [
            'phone_number_id' => '109876543210',
            'verified_name' => 'Bamcom Real Estate Ltd',
            'quality_rating' => 'GREEN',
            'status' => WhatsAppAccountStatus::Connected->value,
        ]);
    }

    public function test_sync_templates_endpoint_pulls_templates_into_database(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/987654321098/message_templates*' => Http::response([
                'data' => [
                    [
                        'id' => '5566778899',
                        'name' => 'inspection_booked_template',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'language' => 'en_US',
                        'components' => [
                            [
                                'type' => 'BODY',
                                'text' => 'Hello {{1}}, your site inspection for {{2}} is booked.',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->from(route('whatsapp.settings.index'))
            ->post(route('whatsapp.settings.sync-templates'));

        $response->assertRedirect(route('whatsapp.settings.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'inspection_booked_template',
            'meta_template_id' => '5566778899',
        ]);
    }
}
