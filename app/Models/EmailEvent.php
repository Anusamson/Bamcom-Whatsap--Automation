<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Email Delivery / Engagement Event Model.
 *
 * @property int $id
 * @property int $email_message_id
 * @property string $event_type
 * @property ?string $provider_event_id
 * @property ?array<string, mixed> $payload
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EmailEvent extends Model
{
    use HasFactory;

    protected $table = 'email_events';

    protected $fillable = [
        'email_message_id',
        'contact_id',
        'email_campaign_id',
        'event_type',
        'provider_event_id',
        'payload',
        'ip_address',
        'user_agent',
        'link_url',
        'bounce_type',
        'bounce_subtype',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
