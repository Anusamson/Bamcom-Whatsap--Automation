<?php

namespace App\Models;

use App\Enums\EmailMessageStatus;
use App\Enums\EmailMessageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Outbound Email Message Model.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $email_account_id
 * @property ?int $contact_id
 * @property ?int $user_id
 * @property ?int $email_template_id
 * @property string $to_email
 * @property ?string $to_name
 * @property string $from_email
 * @property string $from_name
 * @property ?string $reply_to_email
 * @property string $subject
 * @property string $body_html
 * @property ?string $body_plain
 * @property EmailMessageType $type
 * @property EmailMessageStatus $status
 * @property ?string $provider_message_id
 * @property ?array<string, mixed> $provider_response
 * @property ?string $error_message
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $opened_at
 * @property ?Carbon $clicked_at
 * @property ?Carbon $bounced_at
 * @property ?Carbon $complained_at
 * @property ?array<string, mixed> $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class EmailMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_messages';

    protected $fillable = [
        'uuid',
        'email_account_id',
        'contact_id',
        'user_id',
        'email_template_id',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'reply_to_email',
        'subject',
        'body_html',
        'body_plain',
        'type',
        'status',
        'provider_message_id',
        'provider_response',
        'error_message',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'bounced_at',
        'complained_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmailMessageType::class,
            'status' => EmailMessageStatus::class,
            'provider_response' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmailEvent::class)->orderBy('occurred_at', 'asc');
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', EmailMessageStatus::Queued);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', EmailMessageStatus::Sent);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', EmailMessageStatus::Failed);
    }
}
