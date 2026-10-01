<?php

namespace App\Http\Requests\Email;

use App\Enums\EmailTemplateCategory;
use App\Enums\EmailTemplateStatus;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('emailTemplate') ?? $this->route('email_template');

        return $this->user()?->can('update', $template ?? EmailTemplate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body_html' => ['sometimes', 'required', 'string'],
            'body_plain' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', Rule::enum(EmailTemplateCategory::class)],
            'status' => ['nullable', Rule::enum(EmailTemplateStatus::class)],
            'variables' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Enforce CAN-SPAM compliance for marketing templates.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $categoryValue = $this->input('category', $this->route('emailTemplate')?->category?->value);
                $category = EmailTemplateCategory::tryFrom((string) $categoryValue);

                if ($category && $category->requiresUnsubscribe()) {
                    $bodyHtml = (string) $this->input('body_html', $this->route('emailTemplate')?->body_html ?? '');
                    $hasUnsubscribe = (bool) preg_match('/\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i', $bodyHtml);

                    if (! $hasUnsubscribe) {
                        $validator->errors()->add(
                            'body_html',
                            'Every marketing, newsletter, promotion, and re-engagement template must include an unsubscribe link (e.g., {{ unsubscribe_url }}).'
                        );
                    }
                }
            },
        ];
    }
}
