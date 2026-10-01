<?php

namespace App\Http\Requests\Email;

use App\Enums\EmailMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_email' => ['required', 'email', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required_without:email_template_id', 'nullable', 'string'],
            'body_plain' => ['nullable', 'string'],
            'type' => ['nullable', Rule::enum(EmailMessageType::class)],
            'email_account_id' => ['nullable', 'integer', 'exists:email_accounts,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'email_template_id' => ['nullable', 'integer', 'exists:email_templates,id'],
            'template_variables' => ['nullable', 'array'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
