<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Real Estate Estate Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string $location
 * @property ?string $city
 * @property string $state
 * @property ?string $landmarks
 * @property string $title_document
 * @property ?string $description
 * @property ?array<string> $features
 * @property ?string $cover_image
 * @property string $status
 * @property ?string $total_land_size
 * @property-read Collection<int, Property> $properties
 * @property-read Collection<int, PropertyMedia> $media
 */
class Estate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'location',
        'city',
        'state',
        'landmarks',
        'title_document',
        'description',
        'features',
        'cover_image',
        'status',
        'total_land_size',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $estate): void {
            if (empty($estate->uuid)) {
                $estate->uuid = (string) Str::uuid();
            }
            if (empty($estate->slug)) {
                $estate->slug = Str::slug($estate->name);
            }
        });
    }

    /**
     * Properties and plots situated within this estate.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Media assets belonging to this estate.
     *
     * @return HasMany<PropertyMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class);
    }

    /**
     * Scope for active estates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Text search scope.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhere('landmarks', 'like', "%{$term}%")
                ->orWhere('title_document', 'like', "%{$term}%");
        });
    }

    /**
     * Resolve route binding supporting numeric ID, slug, or UUID.
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

        return $this->where('uuid', (string) $value)
            ->orWhere('slug', (string) $value)
            ->first();
    }
}
