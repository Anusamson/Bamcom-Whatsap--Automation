<?php

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Services\Contact\PhoneNormalizerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Contact Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $first_name
 * @property ?string $last_name
 * @property string $phone
 * @property ?string $email
 * @property ?string $location
 * @property ?string $occupation
 * @property string $preferred_language
 * @property LeadSource $lead_source
 * @property ?int $assigned_user_id
 * @property ContactStatus $status
 * @property ?Carbon $last_contact_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read string $full_name
 * @property-read string $initials
 * @property-read string $formatted_phone
 * @property-read string $whatsapp_url
 * @property-read ?User $assignedUser
 */
class Contact extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'phone',
        'email',
        'location',
        'occupation',
        'preferred_language',
        'lead_source',
        'assigned_user_id',
        'status',
        'last_contact_at',
    ];

    /**
     * Attributes appended to arrays and JSON.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
        'initials',
        'formatted_phone',
        'whatsapp_url',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'lead_source' => LeadSource::class,
            'last_contact_at' => 'datetime',
        ];
    }

    /**
     * Auto-generate UUID and normalize phone upon model creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            if (empty($contact->uuid)) {
                $contact->uuid = (string) Str::uuid();
            }

            if (! empty($contact->phone)) {
                $contact->phone = app(PhoneNormalizerService::class)->normalize($contact->phone);
            }
        });

        static::updating(function (Contact $contact): void {
            if ($contact->isDirty('phone') && ! empty($contact->phone)) {
                $contact->phone = app(PhoneNormalizerService::class)->normalize($contact->phone);
            }
        });
    }

    /**
     * The sales agent or team member assigned to this contact.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Sales opportunities associated with this contact over time.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Deals and financial opportunities associated with this contact.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)->latest();
    }

    /**
     * Alias for deals.
     *
     * @return HasMany<Deal, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->deals();
    }

    /**
     * Touch the last contact touchpoint timestamp.
     */
    public function touchLastContact(): self
    {
        $this->update(['last_contact_at' => now()]);

        return $this;
    }

    /**
     * Compute full name.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim(sprintf('%s %s', $this->first_name, (string) $this->last_name))
        );
    }

    /**
     * Compute contact initials.
     */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $first = mb_substr($this->first_name, 0, 1);
                $last = $this->last_name ? mb_substr($this->last_name, 0, 1) : '';

                return strtoupper($first.$last);
            }
        );
    }

    /**
     * Human-friendly formatted phone display.
     */
    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: fn (): string => app(PhoneNormalizerService::class)->format($this->phone ?? '')
        );
    }

    /**
     * Direct WhatsApp web click-to-chat URL.
     */
    protected function whatsappUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => app(PhoneNormalizerService::class)->toWhatsAppUrl($this->phone ?? '')
        );
    }

    /**
     * Search scope across contact attributes.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('occupation', 'like', "%{$term}%");
        });
    }

    /**
     * Filter by contact status.
     */
    public function scopeStatus(Builder $query, ContactStatus|string $status): Builder
    {
        $value = $status instanceof ContactStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Filter by lead source.
     */
    public function scopeLeadSource(Builder $query, LeadSource|string $source): Builder
    {
        $value = $source instanceof LeadSource ? $source->value : $source;

        return $query->where('lead_source', $value);
    }

    /**
     * Filter by assigned user.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Filter unassigned contacts.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }
}
