<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Property Media Asset Model.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $property_id
 * @property ?int $estate_id
 * @property string $media_type
 * @property string $file_path
 * @property ?string $file_url
 * @property ?string $caption
 * @property bool $is_primary
 * @property int $order_column
 * @property-read ?Property $property
 * @property-read ?Estate $estate
 */
class PropertyMedia extends Model
{
    use HasFactory;

    protected $table = 'property_media';

    protected $fillable = [
        'uuid',
        'property_id',
        'estate_id',
        'media_type',
        'file_path',
        'file_url',
        'caption',
        'is_primary',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'order_column' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            if (empty($media->uuid)) {
                $media->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Parent property, if applicable.
     *
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Parent estate, if applicable.
     *
     * @return BelongsTo<Estate, $this>
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Resolved full accessible URL.
     */
    public function getUrlAttribute(): string
    {
        if (! empty($this->file_url)) {
            return $this->file_url;
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        return asset('storage/'.$this->file_path);
    }
}
