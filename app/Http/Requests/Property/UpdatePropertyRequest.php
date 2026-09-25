<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePropertyRequest extends FormRequest
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
            'estate_id' => ['nullable', 'integer', 'exists:estates,id'],
            'promotion_id' => ['nullable', 'integer', 'exists:promotions,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'property_type' => ['sometimes', 'required', new Enum(PropertyType::class)],
            'plot_size' => ['sometimes', 'required', 'string', 'max:100'],
            'plot_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'regular_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'promo_price' => ['nullable', 'numeric', 'min:0'],
            'initial_deposit' => ['nullable', 'numeric', 'min:0'],
            'payment_plan_summary' => ['nullable', 'string'],
            'payment_plans' => ['nullable', 'array'],
            'title_document' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:100'],
            'availability' => ['nullable', new Enum(PropertyStatus::class)],
            'available_units' => ['nullable', 'integer', 'min:0'],
            'total_units' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:published,draft,archived'],
            'is_featured' => ['nullable', 'boolean'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:10240'],
            'cover_image_url' => ['nullable', 'string', 'max:1000'],
            'remove_cover_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cover_image.image' => 'The cover photo must be an image file.',
            'cover_image.mimes' => 'The cover photo must be in JPEG or PNG format.',
            'cover_image.max' => 'The cover photo may not be greater than 10MB.',
        ];
    }
}
