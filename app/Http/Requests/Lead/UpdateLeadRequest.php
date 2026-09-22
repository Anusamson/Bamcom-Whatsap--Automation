<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLeadRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_source' => ['nullable', new Enum(LeadSource::class)],
            'status' => ['nullable', new Enum(LeadStatus::class)],
            'temperature' => ['nullable', new Enum(LeadTemperature::class)],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'budget_range' => ['nullable', 'string', 'max:100'],
            'purchase_timeline' => ['nullable', new Enum(PurchaseTimeline::class)],
            'preferred_location' => ['nullable', 'string', 'max:255'],
            'property_interest' => ['nullable', 'string', 'max:255'],
            'qualification_status' => ['nullable', new Enum(QualificationStatus::class)],
            'notes' => ['nullable', 'string'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'converted_at' => ['nullable', 'date'],
        ];
    }
}
