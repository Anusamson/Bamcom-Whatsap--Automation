<?php

namespace App\Http\Requests\Deal;

use App\Enums\DealStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDealRequest extends FormRequest
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
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pipeline_id' => ['nullable', 'integer', 'exists:pipelines,id'],
            'pipeline_stage_id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'deal_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'expected_close_date' => ['nullable', 'date'],
            'status' => ['nullable', new Enum(DealStatus::class)],
            'lost_reason' => ['nullable', 'string', 'max:1000'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
