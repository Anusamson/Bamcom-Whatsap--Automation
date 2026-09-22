<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Sales Pipeline Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property bool $is_default
 * @property bool $is_active
 * @property int $order_column
 * @property-read Collection<int, PipelineStage> $stages
 * @property-read Collection<int, Lead> $leads
 */
class Pipeline extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'is_default',
        'is_active',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'order_column' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pipeline): void {
            if (empty($pipeline->uuid)) {
                $pipeline->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Pipeline stages ordered by sequence.
     *
     * @return HasMany<PipelineStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('order_column');
    }

    /**
     * Leads in this sales pipeline.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
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
