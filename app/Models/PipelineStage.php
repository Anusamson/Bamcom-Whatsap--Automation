<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Sales Pipeline Stage Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $pipeline_id
 * @property string $name
 * @property string $slug
 * @property int $order_column
 * @property string $color
 * @property int $probability
 * @property bool $is_won
 * @property bool $is_lost
 * @property-read Pipeline $pipeline
 * @property-read Collection<int, Lead> $leads
 */
class PipelineStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'pipeline_id',
        'name',
        'slug',
        'order_column',
        'color',
        'probability',
        'is_won',
        'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'pipeline_id' => 'integer',
            'order_column' => 'integer',
            'probability' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $stage): void {
            if (empty($stage->uuid)) {
                $stage->uuid = (string) Str::uuid();
            }
            if (empty($stage->slug)) {
                $stage->slug = Str::slug($stage->name, '_');
            }
        });
    }

    /**
     * The parent pipeline.
     *
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Leads currently at this pipeline stage.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'pipeline_stage_id');
    }

    /**
     * Resolve route binding supporting numeric ID or UUID.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->first();
        }

        return $this->where('uuid', (string) $value)->first();
    }
}
