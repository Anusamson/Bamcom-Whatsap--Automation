<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message record sent through a campaign.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $campaign_recipient_id
 * @property ?int $whatsapp_message_id
 * @property string $direction
 * @property ?string $meta_message_id
 * @property string $body
 * @property ?string $template_name
 * @property ?array<string, mixed> $payload
 * @property-read Campaign $campaign
 * @property-read CampaignRecipient $recipient
 * @property-read ?WhatsAppMessage $whatsAppMessage
 */
class CampaignMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'campaign_recipient_id',
        'whatsapp_message_id',
        'direction',
        'meta_message_id',
        'body',
        'template_name',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
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

    public function whatsAppMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
