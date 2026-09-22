<?php

namespace App\Models;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Customer Conversation Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $contact_id
 * @property ?int $assigned_user_id
 * @property ?int $whatsapp_account_id
 * @property ConversationMode $mode
 * @property ConversationStatus $status
 * @property string $channel
 * @property ?string $subject
 * @property ?Carbon $last_message_at
 * @property int $unread_count
 * @property ?array<string, mixed> $metadata
 * @property ?Carbon $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Contact $contact
 * @property-read ?User $assignedUser
 * @property-read ?WhatsAppAccount $account
 * @property-read ?Message $latestMessage
 */
class Conversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'conversations';

    protected $fillable = [
        'uuid',
        'contact_id',
        'assigned_user_id',
        'whatsapp_account_id',
        'mode',
        'status',
        'channel',
        'subject',
        'last_message_at',
        'unread_count',
        'metadata',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'mode' => ConversationMode::class,
            'status' => ConversationStatus::class,
            'metadata' => 'array',
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Associated CRM Contact.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Assigned Sales Rep / Agent handling this conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * WhatsApp Account connected to this conversation.
     *
     * @return BelongsTo<WhatsAppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /**
     * Messages belonging to this conversation.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    /**
     * Most recent message in the conversation.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isOpen(): bool
    {
        return $this->status === ConversationStatus::Open;
    }

    public function isPending(): bool
    {
        return $this->status === ConversationStatus::Pending;
    }

    public function isClosed(): bool
    {
        return $this->status === ConversationStatus::Closed;
    }

    public function isAi(): bool
    {
        return $this->mode === ConversationMode::Ai;
    }

    public function isHuman(): bool
    {
        return $this->mode === ConversationMode::Human;
    }

    public function isHybrid(): bool
    {
        return $this->mode === ConversationMode::Hybrid;
    }

    /**
     * Close the conversation.
     */
    public function close(): self
    {
        $this->update([
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Reopen the conversation.
     */
    public function reopen(): self
    {
        $this->update([
            'status' => ConversationStatus::Open,
            'closed_at' => null,
        ]);

        return $this;
    }

    /**
     * Increment unread message count.
     */
    public function incrementUnread(): self
    {
        $this->increment('unread_count');

        return $this;
    }

    /**
     * Reset unread counter when agent reads conversation.
     */
    public function resetUnread(): self
    {
        $this->update(['unread_count' => 0]);

        return $this;
    }

    /**
     * Scope: Open conversations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open->value);
    }

    /**
     * Scope: Pending conversations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Pending->value);
    }

    /**
     * Scope: Closed conversations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Closed->value);
    }

    /**
     * Scope: Filter by conversation mode.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMode(Builder $query, ConversationMode|string $mode): Builder
    {
        $value = $mode instanceof ConversationMode ? $mode->value : $mode;

        return $query->where('mode', $value);
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

    /**
     * Scope: Filter by assigned user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Scope: Filter unassigned conversations.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }

    /**
     * Scope: Search conversations by contact details or subject.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('subject', 'like', "%{$term}%")
                ->orWhereHas('contact', function (Builder $contactQuery) use ($term): void {
                    $contactQuery->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }
}
