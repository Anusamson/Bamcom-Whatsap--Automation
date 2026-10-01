<?php

namespace App\Services\Email;

use App\Models\Contact;

/**
 * Enterprise Email Template Merging and Plain-Text Rendering Engine.
 */
class EmailTemplateService
{
    /**
     * Render HTML and plain text with merge tag replacements.
     *
     * @param  array<string, mixed>  $variables
     * @return array{html: string, plain: string}
     */
    public function render(string $htmlTemplate, ?string $plainTemplate = null, array $variables = []): array
    {
        $flattenedVariables = $this->flattenVariables($variables);

        $renderedHtml = $this->interpolate($htmlTemplate, $flattenedVariables);

        if (! empty($plainTemplate)) {
            $renderedPlain = $this->interpolate($plainTemplate, $flattenedVariables);
        } else {
            $renderedPlain = $this->convertHtmlToPlainText($renderedHtml);
        }

        return [
            'html' => $renderedHtml,
            'plain' => $renderedPlain,
        ];
    }

    /**
     * Render subject line with merge tags.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderSubject(string $subject, array $variables = []): string
    {
        $flattened = $this->flattenVariables($variables);

        return $this->interpolate($subject, $flattened);
    }

    /**
     * Build standard merge tag variables from Contact and System context.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function buildVariablesForContact(?Contact $contact, array $extra = []): array
    {
        $vars = [
            'app' => [
                'name' => config('app.name', 'Bamcom AI CRM'),
                'url' => config('app.url', 'http://localhost:8000'),
            ],
            'company' => [
                'name' => config('app.name', 'Bamcom Real Estate & Investments'),
                'phone' => '+234 800 BAMCOM CRM',
                'email' => config('services.ses.from_address', 'support@bamcomcrm.com'),
            ],
            'unsubscribe_url' => url('/email/unsubscribe?email='.urlencode($contact?->email ?? '')),
        ];

        if ($contact) {
            $vars['contact'] = [
                'id' => $contact->id,
                'uuid' => $contact->uuid,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name ?? '',
                'full_name' => $contact->full_name,
                'email' => $contact->email ?? '',
                'phone' => $contact->phone ?? '',
                'location' => $contact->location ?? '',
                'occupation' => $contact->occupation ?? '',
            ];
        }

        return array_replace_recursive($vars, $extra);
    }

    /**
     * Replace merge tags in format {{variable.key}} or {variable.key}.
     *
     * @param  array<string, string>  $flattened
     */
    protected function interpolate(string $content, array $flattened): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\-\.]+)\s*\}\}|\{\s*([a-zA-Z0-9_\-\.]+)\s*\}/', function ($matches) use ($flattened) {
            $key = ! empty($matches[1]) ? $matches[1] : $matches[2];

            return $flattened[$key] ?? $matches[0];
        }, $content);
    }

    /**
     * Convert multi-dimensional array to dot notation map.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    public function flattenVariables(array $variables, string $prefix = ''): array
    {
        $result = [];

        foreach ($variables as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenVariables($value, $fullKey));
            } else {
                $result[$fullKey] = (string) ($value ?? '');
            }
        }

        return $result;
    }

    /**
     * Convert rich HTML body into a readable plain-text fallback.
     */
    public function convertHtmlToPlainText(string $html): string
    {
        // 1. Remove script and style tags
        $clean = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $html);

        // 2. Convert links <a href="url">text</a> -> text (url)
        $clean = preg_replace('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $clean);

        // 3. Replace block element closings with newlines
        $clean = preg_replace('/<\/(p|div|tr|h[1-6])>/i', "\n\n", $clean);
        $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);
        $clean = preg_replace('/<li\b[^>]*>/i', '• ', $clean);
        $clean = preg_replace('/<\/li>/i', "\n", $clean);

        // 4. Strip remaining HTML tags
        $text = strip_tags($clean);

        // 5. Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 6. Normalize whitespace and empty lines
        $lines = explode("\n", $text);
        $trimmedLines = array_map('trim', $lines);
        $result = preg_replace("/\n{3,}/", "\n\n", implode("\n", $trimmedLines));

        return trim($result);
    }
}
