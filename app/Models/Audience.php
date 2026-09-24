<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Segmentation Audience Model for Campaigns.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property ?array<string, mixed> $filters
 * @property int $cached_count
 * @property ?int $created_by_user_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $creator
 * @property-read HasMany<Campaign, $this> $campaigns
 */
class Audience extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'filters',
        'cached_count',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'cached_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $audience): void {
            if (empty($audience->uuid)) {
                $audience->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'audience_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
