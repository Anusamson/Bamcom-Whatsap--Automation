<?php

namespace App\Http\Requests\User;

use App\DTOs\User\CreateUserDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in(UserRole::values())],
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
     * Transform validated request into CreateUserDTO.
     */
    public function toDTO(): CreateUserDTO
    {
        return CreateUserDTO::fromArray($this->validated());
    }
}
