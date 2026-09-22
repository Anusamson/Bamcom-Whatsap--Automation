<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * WhatsApp Inbound / Outbound Chat Message Record.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $whatsapp_account_id
 * @property ?int $contact_id
 * @property string $meta_message_id
 * @property string $direction
 * @property string $sender_phone
 * @property string $recipient_phone
 * @property string $message_type
 * @property ?string $body
 * @property ?string $media_url
 * @property ?string $media_mime_type
 * @property string $status
 * @property ?array<string, mixed> $payload
 * @property ?string $error_message
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?WhatsAppAccount $account
 * @property-read ?Contact $contact
 */
class WhatsAppMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'uuid',
        'whatsapp_account_id',
        'contact_id',
        'meta_message_id',
        'direction',
        'sender_phone',
        'recipient_phone',
        'message_type',
        'body',
        'media_url',
        'media_mime_type',
        'status',
        'payload',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Associated WhatsApp Business Account.
     *
     * @return BelongsTo<WhatsAppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /**
     * Associated CRM Contact.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Mark message as delivered.
     */
    public function markDelivered(?Carbon $timestamp = null): self
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => $timestamp ?: now(),
        ]);

        return $this;
    }

    /**
     * Mark message as read.
     */
    public function markRead(?Carbon $timestamp = null): self
    {
        $this->update([
            'status' => 'read',
            'read_at' => $timestamp ?: now(),
        ]);

        return $this;
    }

    /**
     * Mark message as failed.
     */
    public function markFailed(string $reason): self
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $reason,
        ]);

        return $this;
    }

    /**
     * Scope: Inbound messages from leads/contacts.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope: Outbound messages sent to contacts.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope: Filter by contact.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForContact(Builder $query, int $contactId): Builder
    {
        return $query->where('contact_id', $contactId);
    }
}
