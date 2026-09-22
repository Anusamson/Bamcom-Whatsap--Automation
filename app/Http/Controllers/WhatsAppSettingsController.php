<?php

namespace App\Http\Controllers;

use App\Enums\PermissionEnum;
use App\Enums\WhatsAppAccountStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\TemplateService;
use App\Services\WhatsApp\WhatsAppClient;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingsController extends Controller
{
    public function __construct(
        protected WhatsAppClient $client,
        protected TemplateService $templateService
    ) {}

    /**
     * Display WhatsApp Business Platform configuration & connection dashboard.
     * Note: NEVER expose raw access tokens or app secrets to the frontend.
     */
    public function index(): Response
    {
        $user = auth()->user();
        if (! $user?->is_super_admin && ! $user?->can(PermissionEnum::WhatsAppView->value) && ! $user?->can(PermissionEnum::SettingsView->value)) {
            abort(403, 'Unauthorized to view WhatsApp Platform settings.');
        }

        $accounts = WhatsAppAccount::query()
            ->withCount(['templates', 'webhookEvents'])
            ->latest()
            ->get();

        $templates = WhatsAppTemplate::query()
            ->orderBy('name')
            ->get();

        $webhookStats = [
            'total' => WhatsAppWebhookEvent::count(),
            'pending' => WhatsAppWebhookEvent::where('status', 'pending')->count(),
            'processed' => WhatsAppWebhookEvent::where('status', 'processed')->count(),
            'failed' => WhatsAppWebhookEvent::where('status', 'failed')->count(),
        ];

        // Masked token: only indicate presence and security mode
        $hasToken = ! empty(config('whatsapp.access_token'));
        $maskedToken = $hasToken
            ? '•••••••••••••••••••••••• (Secured via .env)'
            : 'Not Configured (Missing WHATSAPP_ACCESS_TOKEN)';

        $settings = [
            'is_configured' => $this->client->isConfigured(),
            'masked_access_token' => $maskedToken,
            'has_access_token' => $hasToken,
            'has_app_secret' => ! empty(config('whatsapp.app_secret')),
            'has_verify_token' => ! empty(config('whatsapp.verify_token')),
            'phone_number_id' => $this->client->getPhoneNumberId(),
            'business_account_id' => $this->client->getBusinessAccountId(),
            'api_version' => $this->client->getApiVersion(),
            'base_url' => config('whatsapp.base_url'),
            'webhook_url' => url('/api/v1/whatsapp/webhook'),
            'verify_token_hint' => $hasToken ? '••••••••••••' : null,
        ];

        return Inertia::render('Settings/WhatsApp', [
            'settings' => $settings,
            'accounts' => $accounts,
            'templates' => $templates,
            'webhookStats' => $webhookStats,
        ]);
    }

    /**
     * Test live connectivity with Meta Graph API.
     */
    public function testConnection(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->is_super_admin && ! $user?->can(PermissionEnum::WhatsAppManage->value) && ! $user?->can(PermissionEnum::SettingsEdit->value)) {
            abort(403, 'Unauthorized to test WhatsApp connection.');
        }

        $result = $this->client->testConnection();

        if ($result['success']) {
            $data = $result['data'] ?? [];
            $phoneId = $this->client->getPhoneNumberId();
            $wabaId = $this->client->getBusinessAccountId();

            // Upsert default account record from verified Meta response
            WhatsAppAccount::updateOrCreate(
                ['phone_number_id' => $phoneId],
                [
                    'name' => $data['verified_name'] ?? 'Bamcom Official WhatsApp',
                    'waba_id' => $wabaId ?: null,
                    'display_phone_number' => $data['display_phone_number'] ?? null,
                    'verified_name' => $data['verified_name'] ?? 'Bamcom Real Estate Ltd',
                    'quality_rating' => $data['quality_rating'] ?? 'GREEN',
                    'status' => WhatsAppAccountStatus::Connected,
                    'is_default' => true,
                    'last_synced_at' => now(),
                    'metadata' => $data,
                ]
            );

            return back()->with('success', 'Meta WhatsApp Cloud API connection verified successfully! 🎉 Phone: '.($data['display_phone_number'] ?? $phoneId));
        }

        return back()->with('error', 'Meta WhatsApp Connection Test failed: '.($result['error'] ?? $result['message']));
    }

    /**
     * Trigger synchronization of pre-approved message templates from Meta WABA.
     */
    public function syncTemplates(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user?->is_super_admin && ! $user?->can(PermissionEnum::WhatsAppManage->value) && ! $user?->can(PermissionEnum::SettingsEdit->value)) {
            abort(403, 'Unauthorized to sync WhatsApp templates.');
        }

        try {
            $result = $this->templateService->syncTemplatesFromMeta();

            return back()->with('success', "Successfully synchronized {$result['synced']} message templates from Meta WhatsApp Business Account.");
        } catch (Exception $e) {
            return back()->with('error', 'Template synchronization failed: '.$e->getMessage());
        }
    }
}
