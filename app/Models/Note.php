<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Note Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $contact_id
 * @property ?int $lead_id
 * @property ?int $user_id
 * @property string $content
 * @property bool $is_pinned
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?User $user
 */
class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'contact_id',
        'lead_id',
        'user_id',
        'content',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The contact this note belongs to.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The sales lead associated with this note.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Author / user who authored this note.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pinned notes.
     */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for chronological listing with pinned first.
     */
    public function scopePriorityOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')->latest('created_at');
    }
}
