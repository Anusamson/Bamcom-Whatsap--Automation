<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * WhatsApp Broadcast & Nurture Campaign Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property CampaignStatus $status
 * @property ?int $audience_id
 * @property ?int $whatsapp_template_id
 * @property string $message_type
 * @property ?string $message_content
 * @property ?array<string, mixed> $template_parameters
 * @property int $batch_size
 * @property int $batch_delay_seconds
 * @property ?Carbon $scheduled_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $paused_at
 * @property ?string $cancellation_reason
 * @property int $total_recipients
 * @property int $processed_recipients
 * @property int $sent_count
 * @property int $delivered_count
 * @property int $read_count
 * @property int $failed_count
 * @property int $opted_out_count
 * @property ?int $created_by_user_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Audience $audience
 * @property-read ?WhatsAppTemplate $template
 * @property-read ?User $creator
 * @property-read HasMany<CampaignRecipient, $this> $recipients
 * @property-read HasMany<CampaignMessage, $this> $messages
 * @property-read HasMany<CampaignEvent, $this> $events
 */
class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'audience_id',
        'whatsapp_template_id',
        'message_type',
        'message_content',
        'template_parameters',
        'batch_size',
        'batch_delay_seconds',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'paused_at',
        'cancellation_reason',
        'total_recipients',
        'processed_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'opted_out_count',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'template_parameters' => 'array',
            'batch_size' => 'integer',
            'batch_delay_seconds' => 'integer',
            'total_recipients' => 'integer',
            'processed_recipients' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'failed_count' => 'integer',
            'opted_out_count' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class, 'audience_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CampaignEvent::class, 'campaign_id')->latest('created_at');
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Running->value);
    }

    public function isDraft(): bool
    {
        return $this->status === CampaignStatus::Draft;
    }

    public function isScheduled(): bool
    {
        return $this->status === CampaignStatus::Scheduled;
    }

    public function isRunning(): bool
    {
        return $this->status === CampaignStatus::Running;
    }

    public function isPaused(): bool
    {
        return $this->status === CampaignStatus::Paused;
    }

    public function isCompleted(): bool
    {
        return $this->status === CampaignStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === CampaignStatus::Cancelled;
    }

    public function deliveryRate(): float
    {
        if ($this->sent_count === 0) {
            return 0.0;
        }

        return round(($this->delivered_count / $this->sent_count) * 100, 1);
    }

    public function readRate(): float
    {
        if ($this->delivered_count === 0) {
            return 0.0;
        }

        return round(($this->read_count / $this->delivered_count) * 100, 1);
    }
}
