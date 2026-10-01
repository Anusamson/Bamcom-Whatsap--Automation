<?php

namespace App\Http\Requests\Email;

use App\Enums\EmailSuppressionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailSuppressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', Rule::enum(EmailSuppressionReason::class)],
            'details' => ['nullable', 'string', 'max:1000'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
        ];
    }
}
