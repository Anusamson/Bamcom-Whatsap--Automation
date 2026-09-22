<?php

namespace App\Models;

use App\Enums\WhatsAppAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Meta WhatsApp Business Account / Phone Number Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $phone_number_id
 * @property ?string $waba_id
 * @property ?string $display_phone_number
 * @property ?string $verified_name
 * @property string $quality_rating
 * @property WhatsAppAccountStatus $status
 * @property bool $is_default
 * @property ?Carbon $webhook_verified_at
 * @property ?Carbon $last_synced_at
 * @property ?array<string, mixed> $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class WhatsAppAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'uuid',
        'name',
        'phone_number_id',
        'waba_id',
        'display_phone_number',
        'verified_name',
        'quality_rating',
        'status',
        'is_default',
        'webhook_verified_at',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppAccountStatus::class,
            'is_default' => 'boolean',
            'webhook_verified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Synced message templates associated with this account or WABA.
     *
     * @return HasMany<WhatsAppTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }

    /**
     * Webhook audit events received for this account.
     *
     * @return HasMany<WhatsAppWebhookEvent, $this>
     */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WhatsAppWebhookEvent::class, 'whatsapp_account_id');
    }

    /**
     * Conversations routed through this WhatsApp Account line.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'whatsapp_account_id');
    }

    /**
     * Scope: Only accounts currently marked as connected.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', WhatsAppAccountStatus::Connected);
    }

    /**
     * Scope: The default configured line.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Resolve route binding by either ID or UUID.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            return $this->where('id', $value)->first();
        }

        return $this->where('uuid', $value)->first();
    }
}
