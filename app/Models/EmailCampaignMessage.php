<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pivot / tracking record mapping campaigns to recipients and messages.
 *
 * @property int $id
 * @property int $email_campaign_id
 * @property int $email_campaign_recipient_id
 * @property int $email_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EmailCampaign $campaign
 * @property-read EmailCampaignRecipient $recipient
 * @property-read EmailMessage $message
 */
class EmailCampaignMessage extends Model
{
    use HasFactory;

    protected $table = 'email_campaign_messages';

    protected $fillable = [
        'email_campaign_id',
        'email_campaign_recipient_id',
        'email_message_id',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(EmailCampaignRecipient::class, 'email_campaign_recipient_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
