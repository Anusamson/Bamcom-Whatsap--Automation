<?php

namespace App\Models;

use App\Enums\EmailCampaignRecipientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CRM Email Campaign Individual Recipient Model.
 *
 * @property int $id
 * @property int $email_campaign_id
 * @property int $contact_id
 * @property string $email
 * @property EmailCampaignRecipientStatus $status
 * @property ?string $skip_reason
 * @property ?int $email_message_id
 * @property int $batch_number
 * @property ?Carbon $queued_at
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $opened_at
 * @property ?Carbon $clicked_at
 * @property ?Carbon $failed_at
 * @property ?string $error_message
 * @property ?array<string, mixed> $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EmailCampaign $campaign
 * @property-read Contact $contact
 * @property-read ?EmailMessage $emailMessage
 */
class EmailCampaignRecipient extends Model
{
    use HasFactory;

    protected $table = 'email_campaign_recipients';

    protected $fillable = [
        'email_campaign_id',
        'contact_id',
        'email',
        'status',
        'skip_reason',
        'email_message_id',
        'batch_number',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'failed_at',
        'bounced_at',
        'complained_at',
        'unsubscribed_at',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailCampaignRecipientStatus::class,
            'batch_number' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'failed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Relationship to parent EmailCampaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }

    /**
     * Relationship to Contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Relationship to dispatched EmailMessage.
     */
    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
