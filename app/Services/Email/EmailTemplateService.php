<?php

namespace App\Services\Email;

use App\Enums\EmailTemplateCategory;
use App\Enums\InspectionStatus;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Secure Enterprise Email Template Rendering & Merging Engine.
 *
 * Enforces CAN-SPAM marketing compliance, prevents arbitrary PHP execution,
 * injects responsive preheaders and Bamcom-branded headers/footers, and
 * maps dynamic variables for Contacts, Agents, Properties, and Inspections.
 */
class EmailTemplateService
{
    /**
     * Render HTML and plain text with secure merge tag replacements.
     *
     * @param  array<string, mixed>  $variables
     * @return array{html: string, plain: string}
     */
    public function render(
        string $htmlTemplate,
        ?string $plainTemplate = null,
        array $variables = [],
        ?string $preheader = null,
        bool $wrapWithBrand = false
    ): array {
        // Sanitize to prevent arbitrary script or PHP execution
        $safeHtml = $this->sanitizeTemplate($htmlTemplate);
        $safePlain = $plainTemplate ? $this->sanitizeTemplate($plainTemplate) : null;

        $flattenedVariables = $this->flattenVariables($variables);

        // Preheader HTML insertion if present
        $preheaderHtml = '';
        if (! empty($preheader)) {
            $renderedPreheader = $this->interpolate($this->sanitizeTemplate($preheader), $flattenedVariables);
            $preheaderHtml = '
                <!-- Hidden Preheader Preview Text -->
                <div style="display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;">
                    '.htmlspecialchars($renderedPreheader, ENT_QUOTES, 'UTF-8').'
                </div>
            ';
        }

        // Interpolate content
        $interpolatedHtml = $this->interpolate($safeHtml, $flattenedVariables);

        // Optional wrap with reusable Bamcom-branded header and footer boilerplate
        if ($wrapWithBrand) {
            $finalHtml = $this->wrapWithBamcomBrand($interpolatedHtml, [
                'preheader_html' => $preheaderHtml,
                'unsubscribe_url' => $flattenedVariables['unsubscribe_url'] ?? url('/email/unsubscribe'),
            ]);
        } else {
            $finalHtml = $preheaderHtml.$interpolatedHtml;
        }

        // Generate or interpolate plain text fallback
        if (! empty($safePlain)) {
            $renderedPlain = $this->interpolate($safePlain, $flattenedVariables);
        } else {
            $renderedPlain = $this->convertHtmlToPlainText($finalHtml);
        }

        return [
            'html' => $finalHtml,
            'plain' => $renderedPlain,
        ];
    }

    /**
     * Render subject line with dynamic variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderSubject(string $subject, array $variables = []): string
    {
        $safeSubject = $this->sanitizeTemplate($subject);
        $flattened = $this->flattenVariables($variables);

        return $this->interpolate($safeSubject, $flattened);
    }

    /**
     * Build dynamic variables for Contact, Agent, Property, and Inspection.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function buildVariables(
        ?Contact $contact = null,
        ?User $agent = null,
        ?Property $property = null,
        ?Inspection $inspection = null,
        array $extra = []
    ): array {
        $unsubscribeUrl = url('/email/unsubscribe'.($contact ? '?email='.urlencode($contact->email) : ''));

        $vars = [
            'app' => [
                'name' => config('app.name', 'Bamcom AI CRM'),
                'url' => config('app.url', 'http://localhost:8000'),
            ],
            'company' => [
                'name' => 'Bamcom Real Estate & Investments Ltd',
                'phone' => '+234 800 BAMCOM CRM',
                'email' => config('services.ses.from_address', 'support@bamcomcrm.com'),
                'address' => 'Plot 12, Admiralty Way, Lekki Phase 1, Lagos, Nigeria',
                'website' => config('app.url', 'http://localhost:8000'),
            ],
            'unsubscribe_url' => $unsubscribeUrl,
        ];

        // Contact Variables (e.g. {{ contact.first_name }})
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

        // Sales Representative / Agent Variables (e.g. {{ agent.name }})
        if ($agent) {
            $vars['agent'] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone ?? '+234 800 BAMCOM AGENT',
                'role' => $agent->role?->label() ?? 'Sales Executive',
            ];
        }

        // Property Variables (e.g. {{ property.name }}, {{ property.price }})
        if ($property) {
            $formattedPrice = '₦'.number_format($property->activePrice?->amount ?? 0, 2);
            $vars['property'] = [
                'id' => $property->id,
                'uuid' => $property->uuid,
                'name' => $property->title,
                'title' => $property->title,
                'price' => $formattedPrice,
                'location' => $property->location ?? 'Lekki Peninsula, Lagos',
                'type' => is_string($property->property_type) ? $property->property_type : $property->property_type?->label() ?? 'Luxury Residential',
                'plot_size' => $property->plot_size ?? '600 sqm',
                'reference' => 'BAM-'.str_pad((string) $property->id, 5, '0', STR_PAD_LEFT),
            ];
        }

        // Inspection Variables (e.g. {{ inspection.date }}, {{ inspection.time }})
        if ($inspection) {
            $formattedDate = $inspection->inspection_date instanceof Carbon
                ? $inspection->inspection_date->toFormattedDateString()
                : (string) $inspection->inspection_date;

            $vars['inspection'] = [
                'id' => $inspection->id,
                'uuid' => $inspection->uuid,
                'date' => $formattedDate,
                'time' => $inspection->inspection_time ?? '10:00 AM',
                'location' => $inspection->meeting_point ?? $inspection->estate_name ?? 'Estate Sales Office',
                'meeting_point' => $inspection->meeting_point ?? 'Main Gate Security Pavilion',
                'status' => $inspection->status instanceof InspectionStatus
                    ? $inspection->status->label()
                    : (string) $inspection->status,
                'notes' => $inspection->customer_notes ?? '',
            ];
        }

        return array_replace_recursive($vars, $extra);
    }

    /**
     * Provide rich, realistic mock data for previewing and test sending.
     *
     * @return array<string, mixed>
     */
    public function generateSampleVariables(): array
    {
        return [
            'app' => [
                'name' => 'Bamcom AI CRM',
                'url' => config('app.url', 'http://localhost:8000'),
            ],
            'company' => [
                'name' => 'Bamcom Real Estate & Investments Ltd',
                'phone' => '+234 800 226 2662',
                'email' => 'sales@bamcomcrm.com',
                'address' => 'Plot 12, Admiralty Way, Lekki Phase 1, Lagos, Nigeria',
                'website' => 'https://bamcomcrm.com',
            ],
            'contact' => [
                'first_name' => 'Babajide',
                'last_name' => 'Adeleke',
                'full_name' => 'Babajide Adeleke',
                'email' => 'babajide.adeleke@example.com',
                'phone' => '+234 803 123 4567',
                'location' => 'Victoria Island, Lagos',
                'occupation' => 'Chief Technology Officer',
            ],
            'agent' => [
                'name' => 'Kemi Alabi',
                'email' => 'kemi.alabi@bamcomcrm.com',
                'phone' => '+234 812 987 6543',
                'role' => 'Senior Property Investment Specialist',
            ],
            'property' => [
                'name' => 'The Grandview Waterfront Villa',
                'title' => 'The Grandview Waterfront Villa',
                'price' => '₦185,000,000',
                'location' => 'Admiralty Foreshore, Lekki Phase 1, Lagos',
                'type' => '5-Bedroom Fully Detached Luxury Duplex',
                'plot_size' => '850 sqm',
                'reference' => 'BAM-10492',
            ],
            'inspection' => [
                'date' => 'Saturday, 12th October 2026',
                'time' => '11:00 AM (WAT)',
                'location' => 'Grandview Waterfront Site Entrance, Lekki Phase 1',
                'meeting_point' => 'VIP Reception Lobby',
                'status' => 'Confirmed',
                'notes' => 'Client requested escort for property architect.',
            ],
            'unsubscribe_url' => url('/email/unsubscribe?email=babajide.adeleke%40example.com'),
        ];
    }

    /**
     * Backward-compatible helper for Contact context.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function buildVariablesForContact(?Contact $contact, array $extra = []): array
    {
        return $this->buildVariables(contact: $contact, extra: $extra);
    }

    /**
     * Enforce marketing compliance: marketing templates must include unsubscribe links.
     *
     * @throws ValidationException
     */
    public function validateMarketingCompliance(EmailTemplateCategory|string $category, string $bodyHtml): void
    {
        $categoryEnum = $category instanceof EmailTemplateCategory
            ? $category
            : (EmailTemplateCategory::tryFrom((string) $category) ?? EmailTemplateCategory::Marketing);

        if ($categoryEnum->requiresUnsubscribe()) {
            $hasUnsubscribe = (bool) preg_match('/\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i', $bodyHtml);

            if (! $hasUnsubscribe) {
                throw ValidationException::withMessages([
                    'body_html' => [
                        'Marketing and promotional templates must contain an unsubscribe link (e.g. <a href="{{ unsubscribe_url }}">Unsubscribe</a>) for CAN-SPAM and GDPR compliance.',
                    ],
                ]);
            }
        }
    }

    /**
     * Wrap custom HTML body into a responsive Bamcom-branded email template.
     *
     * @param  array<string, mixed>  $options
     */
    public function wrapWithBamcomBrand(string $bodyHtml, array $options = []): string
    {
        $preheaderHtml = $options['preheader_html'] ?? '';
        $unsubscribeUrl = $options['unsubscribe_url'] ?? url('/email/unsubscribe');
        $companyName = 'Bamcom Real Estate & Investments Ltd';
        $companyAddress = 'Plot 12, Admiralty Way, Lekki Phase 1, Lagos, Nigeria';
        $companyPhone = '+234 800 BAMCOM CRM';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Bamcom AI CRM</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; max-width: 100%; }
        a { color: #0284c7; text-decoration: underline; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: 0 !important; }
            .content-padding { padding: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc;">
    '.$preheaderHtml.'
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; padding: 24px 0;">
        <tr>
            <td align="center">
                <!-- Main Card Container (600px max) -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" class="email-container" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
                    <!-- Bamcom Header Component -->
                    <tr>
                        <td style="background-color: #0f172a; padding: 24px 32px; text-align: left; border-bottom: 3px solid #0284c7;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <div style="font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                            BAMCOM <span style="color: #38bdf8;">CRM</span>
                                        </div>
                                        <div style="font-size: 11px; color: #94a3b8; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 2px;">
                                            Real Estate &bull; Site Inspections &bull; Investments
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content Area -->
                    <tr>
                        <td class="content-padding" style="padding: 32px; font-size: 15px; line-height: 24px; color: #334155;">
                            '.$bodyHtml.'
                        </td>
                    </tr>

                    <!-- Bamcom Reusable Footer Component -->
                    <tr>
                        <td style="background-color: #f1f5f9; padding: 24px 32px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; line-height: 18px; color: #64748b;">
                            <p style="margin: 0 0 8px 0; font-weight: 600; color: #475569;">'.htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8').'</p>
                            <p style="margin: 0 0 8px 0;">'.htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8').'</p>
                            <p style="margin: 0 0 12px 0;">Customer Care: <a href="tel:'.urlencode($companyPhone).'" style="color: #0284c7; text-decoration: none;">'.$companyPhone.'</a></p>
                            
                            <div style="border-top: 1px solid #cbd5e1; margin: 16px 0; padding-top: 12px; font-size: 11px; color: #94a3b8;">
                                You are receiving this official communication as an esteemed client of Bamcom AI CRM.<br>
                                <a href="'.htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8').'" style="color: #64748b; text-decoration: underline;">Unsubscribe from marketing emails</a> &bull;
                                <a href="'.htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8').'" style="color: #64748b; text-decoration: underline;">Communication Preferences</a>
                            </div>
                            <p style="margin: 0; font-size: 10px; color: #94a3b8;">&copy; '.date('Y').' Bamcom Real Estate Ltd. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Neutralize arbitrary PHP tags and script injection to prevent code execution.
     */
    public function sanitizeTemplate(string $content): string
    {
        // Strip entire PHP code blocks
        $sanitized = preg_replace('/<\?(?:php|=)?[\s\S]*?\?>/i', '', $content);

        // Strip ASP tag blocks
        $sanitized = preg_replace('/<%(?:=)?[\s\S]*?%>/i', '', $sanitized);

        // Strip any residual dangling opening or closing PHP/ASP markers
        $sanitized = preg_replace('/<\?(?:php|=)?|<\%|\?>|%\>/i', '', $sanitized);

        // Strip script tags and their inner javascript completely
        $sanitized = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/is', '', $sanitized);

        return (string) $sanitized;
    }

    /**
     * Replace dynamic tokens in format {{ variable.key }} or { variable.key }.
     *
     * @param  array<string, string>  $flattened
     */
    protected function interpolate(string $content, array $flattened): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\-\.]+)\s*\}\}|\{\s*([a-zA-Z0-9_\-\.]+)\s*\}/', function ($matches) use ($flattened) {
            $key = ! empty($matches[1]) ? trim($matches[1]) : trim($matches[2]);

            return $flattened[$key] ?? $matches[0];
        }, $content);
    }

    /**
     * Convert multi-dimensional array into dot notation lookup map.
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
        $clean = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $html);
        $clean = preg_replace('/<a\b[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $clean);
        $clean = preg_replace('/<\/(p|div|tr|h[1-6])>/i', "\n\n", $clean);
        $clean = preg_replace('/<br\s*\/?>/i', "\n", $clean);
        $clean = preg_replace('/<li\b[^>]*>/i', '• ', $clean);
        $clean = preg_replace('/<\/li>/i', "\n", $clean);

        $text = strip_tags($clean);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = explode("\n", $text);
        $trimmedLines = array_map('trim', $lines);
        $result = preg_replace("/\n{3,}/", "\n\n", implode("\n", $trimmedLines));

        return trim($result);
    }
}
