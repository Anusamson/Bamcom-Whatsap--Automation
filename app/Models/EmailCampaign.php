<?php

namespace App\Models;

use App\Enums\EmailCampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Email Marketing Campaign Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property EmailCampaignStatus $status
 * @property ?int $email_template_id
 * @property string $subject
 * @property ?string $preheader
 * @property string $body_html
 * @property ?string $body_plain
 * @property ?int $smart_list_id
 * @property ?array<string, mixed> $segment_criteria
 * @property ?Carbon $scheduled_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $paused_at
 * @property ?Carbon $cancelled_at
 * @property ?string $cancellation_reason
 * @property int $batch_size
 * @property int $total_recipients
 * @property int $eligible_recipients
 * @property int $skipped_recipients
 * @property int $sent_count
 * @property int $delivered_count
 * @property int $failed_count
 * @property int $opened_count
 * @property int $clicked_count
 * @property int $bounced_count
 * @property int $complained_count
 * @property int $unsubscribed_count
 * @property ?int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?EmailTemplate $template
 * @property-read ?SmartList $smartList
 * @property-read ?User $creator
 * @property-read HasMany<EmailCampaignRecipient, $this> $recipients
 * @property-read HasMany<EmailCampaignMessage, $this> $messages
 */
class EmailCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_campaigns';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'email_template_id',
        'subject',
        'preheader',
        'body_html',
        'body_plain',
        'smart_list_id',
        'segment_criteria',
        'scheduled_at',
        'started_at',
        'completed_at',
        'paused_at',
        'cancelled_at',
        'cancellation_reason',
        'batch_size',
        'total_recipients',
        'eligible_recipients',
        'skipped_recipients',
        'sent_count',
        'delivered_count',
        'failed_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'complained_count',
        'unsubscribed_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmailCampaignStatus::class,
            'segment_criteria' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'batch_size' => 'integer',
            'total_recipients' => 'integer',
            'eligible_recipients' => 'integer',
            'skipped_recipients' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'failed_count' => 'integer',
            'opened_count' => 'integer',
            'clicked_count' => 'integer',
            'bounced_count' => 'integer',
            'complained_count' => 'integer',
            'unsubscribed_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (empty($campaign->status)) {
                $campaign->status = EmailCampaignStatus::Draft;
            }
            if (empty($campaign->batch_size)) {
                $campaign->batch_size = 50;
            }
        });
    }

    /**
     * Relationship to EmailTemplate.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    /**
     * Relationship to SmartList.
     */
    public function smartList(): BelongsTo
    {
        return $this->belongsTo(SmartList::class, 'smart_list_id');
    }

    /**
     * Relationship to campaign creator User.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship to recipients.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class, 'email_campaign_id');
    }

    /**
     * Relationship to campaign messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailCampaignMessage::class, 'email_campaign_id');
    }

    /**
     * Scope campaigns by status.
     *
     * @param  Builder<EmailCampaign>  $query
     */
    public function scopeStatus(Builder $query, EmailCampaignStatus|string $status): void
    {
        $value = $status instanceof EmailCampaignStatus ? $status->value : $status;
        $query->where('status', $value);
    }

    /**
     * Scope campaigns ready for scheduled execution.
     *
     * @param  Builder<EmailCampaign>  $query
     */
    public function scopeReadyToSend(Builder $query): void
    {
        $query->where('status', EmailCampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Calculate delivery progress percentage.
     */
    public function progressPercentage(): int
    {
        if ($this->eligible_recipients === 0) {
            return $this->status === EmailCampaignStatus::Completed ? 100 : 0;
        }

        $processed = $this->sent_count + $this->failed_count;
        $percentage = (int) round(($processed / $this->eligible_recipients) * 100);

        return min(100, max(0, $percentage));
    }

    /**
     * Delivery rate percentage (delivered / sent).
     */
    public function deliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0.0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    /**
     * Open rate percentage (opened / delivered).
     */
    public function openRate(): float
    {
        $base = $this->delivered_count > 0 ? $this->delivered_count : $this->sent_count;
        if ($base === 0) {
            return 0.0;
        }

        return round(($this->opened_count / $base) * 100, 2);
    }

    /**
     * Click rate percentage (clicked / delivered).
     */
    public function clickRate(): float
    {
        $base = $this->delivered_count > 0 ? $this->delivered_count : $this->sent_count;
        if ($base === 0) {
            return 0.0;
        }

        return round(($this->clicked_count / $base) * 100, 2);
    }

    /**
     * Click-to-open rate percentage (clicked / opened).
     */
    public function clickToOpenRate(): float
    {
        if ($this->opened_count === 0) {
            return 0.0;
        }

        return round(($this->clicked_count / $this->opened_count) * 100, 2);
    }

    /**
     * Bounce rate percentage (bounced / sent).
     */
    public function bounceRate(): float
    {
        if ($this->sent_count === 0) {
            return 0.0;
        }

        return round(($this->bounced_count / $this->sent_count) * 100, 2);
    }

    /**
     * Unsubscribe rate percentage (unsubscribed / delivered).
     */
    public function unsubscribeRate(): float
    {
        $base = $this->delivered_count > 0 ? $this->delivered_count : $this->sent_count;
        if ($base === 0) {
            return 0.0;
        }

        return round(($this->unsubscribed_count / $base) * 100, 2);
    }

    /**
     * Complete analytics summary derived strictly from stored application data.
     *
     * @return array<string, mixed>
     */
    public function getAnalyticsSummary(): array
    {
        return [
            'recipients' => $this->total_recipients,
            'eligible' => $this->eligible_recipients,
            'skipped' => $this->skipped_recipients,
            'sent' => $this->sent_count,
            'delivered' => $this->delivered_count,
            'failed' => $this->failed_count,
            'opened' => $this->opened_count,
            'clicked' => $this->clicked_count,
            'bounced' => $this->bounced_count,
            'complained' => $this->complained_count,
            'unsubscribed' => $this->unsubscribed_count,
            'delivery_rate' => $this->deliveryRate(),
            'open_rate' => $this->openRate(),
            'click_rate' => $this->clickRate(),
            'click_to_open_rate' => $this->clickToOpenRate(),
            'bounce_rate' => $this->bounceRate(),
            'unsubscribe_rate' => $this->unsubscribeRate(),
            'progress_percentage' => $this->progressPercentage(),
        ];
    }

    /**
     * Check if campaign HTML includes mandatory unsubscribe tag for CAN-SPAM.
     */
    public function hasUnsubscribeLink(): bool
    {
        return (bool) preg_match('/\{\{\s*unsubscribe_url\s*\}\}|\{\s*unsubscribe_url\s*\}|unsubscribe/i', $this->body_html);
    }
}
