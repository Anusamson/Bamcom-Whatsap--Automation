<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/**
 * CRM Tag Model.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 */
class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Associated contacts.
     */
    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'taggable');
    }

    /**
     * Associated leads.
     */
    public function leads(): MorphToMany
    {
        return $this->morphedByMany(Lead::class, 'taggable');
    }
}
