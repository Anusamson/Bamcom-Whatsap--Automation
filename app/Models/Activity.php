<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * CRM Lead Activity Record Model.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $user_id
 * @property ?int $lead_id
 * @property ?int $deal_id
 * @property ?int $contact_id
 * @property string $activity_type
 * @property string $description
 * @property ?array<string, mixed> $properties
 * @property-read ?User $user
 * @property-read ?Lead $lead
 * @property-read ?Deal $deal
 * @property-read ?Contact $contact
 */
class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'lead_id',
        'deal_id',
        'contact_id',
        'activity_type',
        'description',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            if (empty($activity->uuid)) {
                $activity->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * User / Agent who initiated this activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The associated sales opportunity / lead.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The associated deal / opportunity.
     *
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * The associated contact.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
