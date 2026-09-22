<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required_without:template_name', 'nullable', 'string', 'max:4000'],
            'type' => ['nullable', 'string', 'in:text,template'],
            'template_name' => ['required_if:type,template', 'nullable', 'string', 'max:120'],
            'template_parameters' => ['nullable', 'array'],
        ];
    }
}
