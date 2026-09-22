<?php

namespace App\Http\Requests\Team;

use App\DTOs\Team\UpdateTeamDTO;
use App\Enums\TeamType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $this->user()?->can('update', $team) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->route('team')?->id ?? $this->route('team');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($teamId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'required', 'string', Rule::in(TeamType::values())],
            'leader_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['nullable', 'boolean'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * Transform validated request into UpdateTeamDTO.
     */
    public function toDTO(): UpdateTeamDTO
    {
        return UpdateTeamDTO::fromArray($this->validated());
    }
}
