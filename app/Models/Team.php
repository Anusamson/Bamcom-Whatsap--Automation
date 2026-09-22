<?php

namespace App\Models;

use App\Enums\TeamType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'leader_id',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TeamType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Team leader (e.g. Sales Manager or Team Lead).
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Direct users assigned to this team as their primary team.
     */
    public function directUsers(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }

    /**
     * Team members joined via the team_user pivot table.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role_in_team', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Scope query to only active teams.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to sales teams only.
     */
    public function scopeSales(Builder $query): Builder
    {
        return $query->where('type', TeamType::Sales);
    }

    /**
     * Check if this team is a designated sales team.
     */
    public function isSalesTeam(): bool
    {
        return $this->type === TeamType::Sales;
    }
}
