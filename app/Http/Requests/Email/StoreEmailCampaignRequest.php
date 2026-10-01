<?php

namespace App\Http\Requests\Email;

use App\Enums\EmailCampaignStatus;
use App\Models\EmailCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmailCampaign::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_plain' => ['nullable', 'string'],
            'smart_list_id' => ['nullable', 'exists:smart_lists,id'],
            'segment_criteria' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'batch_size' => ['nullable', 'integer', 'min:5', 'max:500'],
            'status' => ['nullable', Rule::enum(EmailCampaignStatus::class)],
        ];
    }

    /**
     * Enforce CAN-SPAM marketing compliance: campaigns require unsubscribe link.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $html = (string) $this->input('body_html', '');
                $hasUnsubscribe = (bool) preg_match('/\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i', $html);

                if (! $hasUnsubscribe) {
                    $validator->errors()->add(
                        'body_html',
                        'Marketing campaigns must include an unsubscribe link (e.g., {{ unsubscribe_url }}) to comply with CAN-SPAM and GDPR regulations.'
                    );
                }
            },
        ];
    }
}
