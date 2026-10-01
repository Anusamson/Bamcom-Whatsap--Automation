<?php

namespace App\Http\Requests\Email;

use App\Enums\EmailCampaignStatus;
use App\Models\EmailCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $campaign = $this->route('emailCampaign') ?? $this->route('email_campaign');

        return $this->user()?->can('update', $campaign ?? EmailCampaign::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body_html' => ['sometimes', 'required', 'string'],
            'body_plain' => ['nullable', 'string'],
            'smart_list_id' => ['nullable', 'exists:smart_lists,id'],
            'segment_criteria' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
            'batch_size' => ['nullable', 'integer', 'min:5', 'max:500'],
            'status' => ['nullable', Rule::enum(EmailCampaignStatus::class)],
        ];
    }

    /**
     * Enforce CAN-SPAM marketing compliance if body_html is modified.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->has('body_html')) {
                    $html = (string) $this->input('body_html', '');
                    $hasUnsubscribe = (bool) preg_match('/\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i', $html);

                    if (! $hasUnsubscribe) {
                        $validator->errors()->add(
                            'body_html',
                            'Marketing campaigns must include an unsubscribe link (e.g., {{ unsubscribe_url }}) to comply with CAN-SPAM and GDPR regulations.'
                        );
                    }
                }
            },
        ];
    }
}
