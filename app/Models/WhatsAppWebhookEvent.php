<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Audit record of incoming raw webhook payloads from Meta.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $whatsapp_account_id
 * @property string $event_type
 * @property ?string $meta_event_id
 * @property ?string $sender_phone
 * @property ?string $recipient_phone
 * @property array<string, mixed> $payload
 * @property ?array<string, mixed> $headers
 * @property ?string $signature
 * @property string $status
 * @property ?Carbon $processed_at
 * @property ?string $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WhatsAppWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'uuid',
        'whatsapp_account_id',
        'event_type',
        'meta_event_id',
        'sender_phone',
        'recipient_phone',
        'payload',
        'headers',
        'signature',
        'status',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Account receiving this event.
     *
     * @return BelongsTo<WhatsAppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /**
     * Mark event as processed.
     */
    public function markProcessed(): self
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);

        return $this;
    }

    /**
     * Mark event as failed with reason.
     */
    public function markFailed(string $error): self
    {
        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
            'error_message' => $error,
        ]);

        return $this;
    }

    /**
     * Scope: Pending webhook events waiting to be processed.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
