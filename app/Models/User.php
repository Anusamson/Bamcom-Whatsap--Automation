<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get or set the user's role attribute safely.
     */
    protected function role(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): UserRole {
                if ($value instanceof UserRole) {
                    return $value;
                }

                if (is_string($value)) {
                    return UserRole::tryFrom($value) ?? match (strtolower(trim($value))) {
                        'super admin', 'superadmin' => UserRole::SuperAdmin,
                        'admin', 'administrator' => UserRole::Admin,
                        'sales manager', 'manager' => UserRole::SalesManager,
                        'sales executive', 'salesexec', 'agent', 'user' => UserRole::SalesExecutive,
                        'customer support', 'support' => UserRole::CustomerSupport,
                        'marketing' => UserRole::Marketing,
                        'inspection officer', 'inspector' => UserRole::InspectionOfficer,
                        'management' => UserRole::Management,
                        default => UserRole::SalesExecutive,
                    };
                }

                return UserRole::SalesExecutive;
            },
            set: fn (mixed $value): string => $value instanceof UserRole ? $value->value : (UserRole::tryFrom((string) $value)?->value ?? (string) $value),
        );
    }

    /**
     * Get or set the user's status attribute safely.
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): UserStatus {
                if ($value instanceof UserStatus) {
                    return $value;
                }

                if (is_string($value)) {
                    return UserStatus::tryFrom($value) ?? UserStatus::Active;
                }

                return UserStatus::Active;
            },
            set: fn (mixed $value): string => $value instanceof UserStatus ? $value->value : (UserStatus::tryFrom((string) $value)?->value ?? (string) $value),
        );
    }

    /**
     * Determine whether the user possesses the Super Admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin->value) || $this->role === UserRole::SuperAdmin;
    }
}
