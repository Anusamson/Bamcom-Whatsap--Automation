<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Email Account Configuration Model.
 *
 * @property int $id
 * @property string $name
 * @property string $provider
 * @property string $from_name
 * @property string $from_email
 * @property ?string $reply_to_email
 * @property ?array<string, mixed> $configuration
 * @property bool $is_default
 * @property bool $is_active
 * @property ?int $daily_quota
 * @property int $sent_today
 * @property ?int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class EmailAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_accounts';

    protected $fillable = [
        'name',
        'provider',
        'from_name',
        'from_email',
        'reply_to_email',
        'configuration',
        'is_default',
        'is_active',
        'daily_quota',
        'sent_today',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'daily_quota' => 'integer',
            'sent_today' => 'integer',
        ];
    }

    /**
     * Scope to active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to default account.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * User who registered the account.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Outbound messages sent via this email account.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }
}
