<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Individual Targeted Recipient inside a Campaign.
 *
 * @property int $id
 * @property string $uuid
 * @property int $campaign_id
 * @property int $contact_id
 * @property ?int $lead_id
 * @property string $phone
 * @property CampaignRecipientStatus $status
 * @property int $batch_number
 * @property ?string $meta_message_id
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $read_at
 * @property ?Carbon $failed_at
 * @property ?string $error_message
 * @property-read Campaign $campaign
 * @property-read Contact $contact
 * @property-read ?Lead $lead
 * @property-read HasMany<CampaignMessage, $this> $messages
 */
class CampaignRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'contact_id',
        'lead_id',
        'phone',
        'status',
        'batch_number',
        'meta_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'batch_number' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $recipient): void {
            if (empty($recipient->uuid)) {
                $recipient->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class, 'campaign_recipient_id');
    }
}
