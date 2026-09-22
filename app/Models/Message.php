<?php

namespace App\Models;

use App\Enums\MessageDeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Message Entity (Inbound & Outbound across channels).
 *
 * @property int $id
 * @property string $uuid
 * @property int $conversation_id
 * @property ?int $contact_id
 * @property string $sender_type
 * @property ?int $sender_id
 * @property ?string $meta_message_id
 * @property string $direction
 * @property ?string $sender_phone
 * @property ?string $recipient_phone
 * @property string $type
 * @property ?string $body
 * @property ?string $media_url
 * @property ?string $media_mime_type
 * @property ?array<string, mixed> $media_metadata
 * @property MessageDeliveryStatus $delivery_status
 * @property bool $is_read
 * @property ?Carbon $read_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $sent_at
 * @property ?string $error_message
 * @property ?array<string, mixed> $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Conversation $conversation
 * @property-read ?Contact $contact
 * @property-read ?User $senderUser
 */
class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'messages';

    protected $fillable = [
        'uuid',
        'conversation_id',
        'contact_id',
        'sender_type',
        'sender_id',
        'meta_message_id',
        'direction',
        'sender_phone',
        'recipient_phone',
        'type',
        'body',
        'media_url',
        'media_mime_type',
        'media_metadata',
        'delivery_status',
        'is_read',
        'read_at',
        'delivered_at',
        'sent_at',
        'error_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'delivery_status' => MessageDeliveryStatus::class,
            'is_read' => 'boolean',
            'media_metadata' => 'array',
            'payload' => 'array',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
            'sent_at' => 'datetime',
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
     * Parent conversation for this message.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Contact communicating in this message.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * User / Agent who sent this message if sender_type is 'user'.
     *
     * @return BelongsTo<User, $this>
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Mark message as delivered.
     */
    public function markDelivered(?Carbon $timestamp = null): self
    {
        $this->update([
            'delivery_status' => MessageDeliveryStatus::Delivered,
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
            'delivery_status' => MessageDeliveryStatus::Read,
            'is_read' => true,
            'read_at' => $timestamp ?: now(),
        ]);

        return $this;
    }

    /**
     * Mark message delivery as failed.
     */
    public function markFailed(string $error): self
    {
        $this->update([
            'delivery_status' => MessageDeliveryStatus::Failed,
            'error_message' => $error,
        ]);

        return $this;
    }

    /**
     * Scope: Inbound messages from contact.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Scope: Outbound messages sent to contact.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope: Unread messages.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
