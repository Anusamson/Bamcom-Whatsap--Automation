<?php

namespace App\Http\Requests\Estate;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateEstateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'landmarks' => ['nullable', 'string', 'max:255'],
            'title_document' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'cover_image' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:active,inactive,completed'],
            'total_land_size' => ['nullable', 'string', 'max:100'],
        ];
    }
}
