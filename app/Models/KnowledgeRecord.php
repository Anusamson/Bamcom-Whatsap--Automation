<?php

namespace App\Models;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Enterprise AI Grounding Knowledge Base Record.
 *
 * Governs business knowledge across 8 strategic real estate categories,
 * strictly filtering out inactive, expired, or pending records from AI inference.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property KnowledgeCategory $category
 * @property string $content
 * @property KnowledgeStatus $status
 * @property int $priority
 * @property Carbon|null $effective_date
 * @property Carbon|null $expiration_date
 * @property array<string>|null $keywords
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class KnowledgeRecord extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'knowledge_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'title',
        'category',
        'content',
        'status',
        'priority',
        'effective_date',
        'expiration_date',
        'keywords',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => KnowledgeCategory::class,
            'status' => KnowledgeStatus::class,
            'priority' => 'integer',
            'effective_date' => 'date',
            'expiration_date' => 'date',
            'keywords' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model to automatically generate a UUID.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The staff member who created this knowledge record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The staff member who last updated this knowledge record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to only records that are strictly active and available for AI consumption.
     *
     * A record is active if and only if:
     * 1. Status is Active
     * 2. Effective date is null or in the past/today
     * 3. Expiration date is null or in the future/today
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', KnowledgeStatus::Active->value)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('effective_date')
                    ->orWhereDate('effective_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', $today);
            });
    }

    /**
     * Scope to filter by specific knowledge category.
     */
    public function scopeCategory(Builder $query, KnowledgeCategory|string $category): Builder
    {
        $value = $category instanceof KnowledgeCategory ? $category->value : $category;

        return $query->where('category', $value);
    }

    /**
     * Scope to order records by priority (highest first) and recent updates.
     */
    public function scopePrioritized(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->latest('updated_at');
    }

    /**
     * Scope to search knowledge records by text query.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%")
                ->orWhere('keywords', 'like', "%{$term}%");
        });
    }

    /**
     * Determine if this record is currently active and usable by AI.
     */
    public function isActive(): bool
    {
        if ($this->status !== KnowledgeStatus::Active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->effective_date !== null && $this->effective_date->startOfDay()->isAfter($today)) {
            return false;
        }

        if ($this->expiration_date !== null && $this->expiration_date->endOfDay()->isBefore($today)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the knowledge record has passed its expiration date.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->endOfDay()->isPast();
    }

    /**
     * Determine if the knowledge record is scheduled for the future.
     */
    public function isScheduled(): bool
    {
        return $this->effective_date !== null && $this->effective_date->startOfDay()->isFuture();
    }
}
