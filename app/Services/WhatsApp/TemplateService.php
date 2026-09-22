<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    public function __construct(
        protected WhatsAppClient $client
    ) {}

    /**
     * Synchronize templates from Meta Business Account into local database.
     *
     * @return array{synced: int, total: int, templates: array<int, string>}
     *
     * @throws Exception
     */
    public function syncTemplatesFromMeta(?WhatsAppAccount $account = null): array
    {
        $wabaId = $account?->waba_id ?: $this->client->getBusinessAccountId();

        $metaResponse = $this->client->fetchTemplates($wabaId);
        $templatesData = $metaResponse['data'] ?? [];

        $syncedCount = 0;
        $templateNames = [];

        foreach ($templatesData as $item) {
            $parsed = $this->parseMetaTemplate($item);

            WhatsAppTemplate::updateOrCreate(
                [
                    'name' => $parsed['name'],
                    'language' => $parsed['language'],
                ],
                [
                    'whatsapp_account_id' => $account?->id,
                    'meta_template_id' => $parsed['meta_template_id'],
                    'category' => $parsed['category'],
                    'status' => $parsed['status'],
                    'header_type' => $parsed['header_type'],
                    'header_content' => $parsed['header_content'],
                    'body_text' => $parsed['body_text'],
                    'footer_text' => $parsed['footer_text'],
                    'buttons' => $parsed['buttons'],
                    'components' => $parsed['components'],
                    'rejection_reason' => $parsed['rejection_reason'],
                    'last_synced_at' => now(),
                ]
            );

            $syncedCount++;
            $templateNames[] = $parsed['name'];
        }

        if ($account) {
            $account->update(['last_synced_at' => now()]);
        }

        Log::info("Synced {$syncedCount} WhatsApp templates from Meta WABA [{$wabaId}].");

        return [
            'synced' => $syncedCount,
            'total' => count($templatesData),
            'templates' => $templateNames,
        ];
    }

    /**
     * Retrieve local templates with optional filtering.
     *
     * @param  array{category?: ?string, status?: ?string, search?: ?string}  $filters
     * @return Collection<int, WhatsAppTemplate>
     */
    public function getTemplates(array $filters = []): Collection
    {
        $query = WhatsAppTemplate::query()->with('account')->orderBy('name');

        if (! empty($filters['category'])) {
            $query->where('category', strtoupper($filters['category']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper($filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('body_text', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Render a preview of a template with provided sample parameters.
     *
     * @param  array<int|string, string>  $variables
     */
    public function renderPreview(WhatsAppTemplate $template, array $variables = []): string
    {
        return $template->render($variables);
    }

    /**
     * Parse raw Meta template payload into structured database attributes.
     *
     * @param  array<string, mixed>  $metaItem
     * @return array<string, mixed>
     */
    protected function parseMetaTemplate(array $metaItem): array
    {
        $components = (array) ($metaItem['components'] ?? []);

        $headerType = null;
        $headerContent = null;
        $bodyText = '';
        $footerText = null;
        $buttons = [];

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $headerType = $component['format'] ?? 'TEXT';
                $headerContent = $component['text'] ?? null;
            } elseif ($type === 'BODY') {
                $bodyText = (string) ($component['text'] ?? '');
            } elseif ($type === 'FOOTER') {
                $footerText = (string) ($component['text'] ?? '');
            } elseif ($type === 'BUTTONS') {
                $buttons = (array) ($component['buttons'] ?? []);
            }
        }

        // Map status safely
        $rawStatus = strtoupper((string) ($metaItem['status'] ?? 'APPROVED'));
        $status = match ($rawStatus) {
            'APPROVED' => WhatsAppTemplateStatus::Approved,
            'PENDING', 'PENDING_DELETION' => WhatsAppTemplateStatus::Pending,
            'REJECTED' => WhatsAppTemplateStatus::Rejected,
            'PAUSED' => WhatsAppTemplateStatus::Paused,
            'DISABLED' => WhatsAppTemplateStatus::Disabled,
            default => WhatsAppTemplateStatus::Approved,
        };

        return [
            'meta_template_id' => $metaItem['id'] ?? null,
            'name' => (string) ($metaItem['name'] ?? ''),
            'category' => (string) ($metaItem['category'] ?? 'UTILITY'),
            'language' => (string) ($metaItem['language'] ?? 'en_US'),
            'status' => $status,
            'header_type' => $headerType,
            'header_content' => $headerContent,
            'body_text' => $bodyText,
            'footer_text' => $footerText,
            'buttons' => $buttons,
            'components' => $components,
            'rejection_reason' => $metaItem['rejected_reason'] ?? null,
        ];
    }
}
