<?php

namespace App\Models;

use App\Enums\CampaignEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit and execution event logged for campaign activity.
 *
 * @property int $id
 * @property int $campaign_id
 * @property ?int $campaign_recipient_id
 * @property CampaignEventType $event_type
 * @property ?string $description
 * @property ?array<string, mixed> $data
 * @property Carbon $created_at
 * @property-read Campaign $campaign
 * @property-read ?CampaignRecipient $recipient
 */
class CampaignEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'campaign_id',
        'campaign_recipient_id',
        'event_type',
        'description',
        'data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => CampaignEventType::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id');
    }
}
