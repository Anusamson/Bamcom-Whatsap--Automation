<?php

namespace App\Models;

use App\Services\SmartList\SmartListQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Smart List Model for dynamic CRM contact segmentation.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property ?string $description
 * @property array<string, mixed> $rule_groups
 * @property string $icon
 * @property string $color
 * @property bool $is_preset
 * @property bool $is_favorite
 * @property int $cached_count
 * @property ?int $created_by_user_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?User $creator
 */
class SmartList extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'rule_groups',
        'icon',
        'color',
        'is_preset',
        'is_favorite',
        'cached_count',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rule_groups' => 'array',
            'is_preset' => 'boolean',
            'is_favorite' => 'boolean',
            'cached_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $smartList): void {
            if (empty($smartList->uuid)) {
                $smartList->uuid = (string) Str::uuid();
            }

            if (empty($smartList->slug)) {
                $baseSlug = Str::slug($smartList->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $smartList->slug = $slug;
            }
        });
    }

    /**
     * User who created this smart list.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Build dynamic Eloquent query over contacts without duplicating records.
     *
     * @return Builder<Contact>
     */
    public function contactsQuery(): Builder
    {
        return app(SmartListQueryBuilder::class)->buildQuery($this->rule_groups ?? []);
    }

    /**
     * Execute the dynamic query and return matching contacts collection.
     *
     * @return Collection<int, Contact>
     */
    public function getContacts(?int $limit = null): Collection
    {
        $query = $this->contactsQuery();

        if (! is_null($limit) && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Calculate live dynamic matching count.
     */
    public function getCount(): int
    {
        return $this->contactsQuery()->count();
    }

    /**
     * Refresh and persist cached count.
     */
    public function refreshCount(): int
    {
        $count = $this->getCount();
        $this->update(['cached_count' => $count]);

        return $count;
    }
}
