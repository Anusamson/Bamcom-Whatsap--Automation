<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'status', 'team_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

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

    /**
     * The primary team assigned to the user (e.g. Sales Team).
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * All teams the user belongs to via team memberships.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot('role_in_team', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Teams where this user is assigned as team leader.
     */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    /**
     * Detailed user CRM profile information.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Contacts assigned to this user.
     */
    public function assignedContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_user_id');
    }

    /**
     * Sales leads assigned to this user.
     */
    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_user_id');
    }

    /**
     * Deals and opportunities assigned to this sales representative.
     *
     * @return HasMany<Deal, $this>
     */
    public function assignedDeals(): HasMany
    {
        return $this->hasMany(Deal::class, 'assigned_user_id');
    }

    /**
     * Site inspections assigned to this user as representative/field agent.
     *
     * @return HasMany<Inspection, $this>
     */
    public function assignedInspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'representative_id');
    }

    /**
     * Conversations assigned to this user/agent.
     *
     * @return HasMany<Conversation, $this>
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /**
     * Alias for assignedDeals.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->assignedDeals();
    }

    /**
     * Tasks assigned to this user.
     *
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_user_id');
    }

    /**
     * Tasks created by this user.
     *
     * @return HasMany<Task, $this>
     */
    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by_id');
    }

    /**
     * Notes authored by this user.
     *
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'user_id');
    }

    /**
     * Determine if the user has any recorded CRM activity.
     * Users with recorded CRM activity cannot be permanently deleted.
     */
    public function hasCrmActivity(): bool
    {
        // 0. Contacts assignment activity
        if (Schema::hasTable('contacts')) {
            $hasContactActivity = DB::table('contacts')
                ->where('assigned_user_id', $this->id)
                ->whereNull('deleted_at')
                ->exists();
            if ($hasContactActivity) {
                return true;
            }
        }

        // 1. Leads management activity
        if (Schema::hasTable('leads')) {
            $hasLeadActivity = DB::table('leads')
                ->where('assigned_user_id', $this->id)
                ->whereNull('deleted_at')
                ->exists();
            if ($hasLeadActivity) {
                return true;
            }
        }

        // 2. Customer relationship activity
        if (Schema::hasTable('customers')) {
            $hasCustomerActivity = DB::table('customers')
                ->where('user_id', $this->id)
                ->orWhere('assigned_to', $this->id)
                ->exists();
            if ($hasCustomerActivity) {
                return true;
            }
        }

        // 3. Support ticket activity
        if (Schema::hasTable('tickets')) {
            $hasTicketActivity = DB::table('tickets')
                ->where('user_id', $this->id)
                ->orWhere('assigned_to', $this->id)
                ->exists();
            if ($hasTicketActivity) {
                return true;
            }
        }

        // 4. Field inspection activity
        if (Schema::hasTable('inspections')) {
            $hasInspectionActivity = DB::table('inspections')
                ->where(function ($q): void {
                    $q->where('representative_id', $this->id)
                        ->orWhere('created_by_id', $this->id);
                })
                ->whereNull('deleted_at')
                ->exists();
            if ($hasInspectionActivity) {
                return true;
            }
        }

        // 4b. Tasks activity
        if (Schema::hasTable('tasks')) {
            $hasTaskActivity = DB::table('tasks')
                ->where(function ($q): void {
                    $q->where('assigned_user_id', $this->id)
                        ->orWhere('created_by_id', $this->id);
                })
                ->whereNull('deleted_at')
                ->exists();
            if ($hasTaskActivity) {
                return true;
            }
        }

        // 5. Team leadership activity
        if ($this->ledTeams()->exists()) {
            return true;
        }

        // 6. Dedicated CRM activity log records
        if (Schema::hasTable('crm_activities')) {
            $hasAuditActivity = DB::table('crm_activities')
                ->where('user_id', $this->id)
                ->exists();
            if ($hasAuditActivity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Disable the user account.
     */
    public function disable(): void
    {
        $this->update(['status' => UserStatus::Inactive]);
    }

    /**
     * Activate the user account.
     */
    public function activate(): void
    {
        $this->update(['status' => UserStatus::Active]);
    }

    /**
     * Determine if the user account is disabled or inactive.
     */
    public function isDisabled(): bool
    {
        return $this->status !== UserStatus::Active;
    }

    /**
     * Scope query to only active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }
}
