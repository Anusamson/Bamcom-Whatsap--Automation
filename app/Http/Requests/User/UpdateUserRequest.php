<?php

namespace App\Http\Requests\User;

use App\DTOs\User\UpdateUserDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $targetUser = $this->route('user');

        return $this->user()?->can('update', $targetUser) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUserId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($targetUserId)],
            'password' => ['nullable', 'string', Password::defaults()],
            'role' => ['sometimes', 'required', 'string', Rule::in(UserRole::values())],
            'status' => ['nullable', 'string', Rule::in(UserStatus::values())],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(UserRole::values())],
        ];
    }

    /**
     * Transform validated request into UpdateUserDTO.
     */
    public function toDTO(): UpdateUserDTO
    {
        return UpdateUserDTO::fromArray($this->validated());
    }
}
