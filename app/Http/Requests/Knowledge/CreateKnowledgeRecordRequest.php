<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateKnowledgeRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('keywords') && is_string($this->keywords)) {
            $parsed = array_values(array_filter(array_map('trim', explode(',', $this->keywords))));
            $this->merge(['keywords' => $parsed]);
        }

        if (! $this->has('priority') || $this->priority === null) {
            $this->merge(['priority' => 0]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(KnowledgeCategory::class)],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(KnowledgeStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'effective_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
