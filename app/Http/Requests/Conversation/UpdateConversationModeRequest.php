<?php

namespace App\Http\Requests\Conversation;

use App\Enums\ConversationMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateConversationModeRequest extends FormRequest
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
            'mode' => ['required', new Enum(ConversationMode::class)],
        ];
    }
}
