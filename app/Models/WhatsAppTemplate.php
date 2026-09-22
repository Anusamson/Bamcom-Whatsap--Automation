<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * WhatsApp Approved / Meta Message Template Model.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $whatsapp_account_id
 * @property ?string $meta_template_id
 * @property string $name
 * @property string $category
 * @property string $language
 * @property WhatsAppTemplateStatus $status
 * @property ?string $header_type
 * @property ?string $header_content
 * @property string $body_text
 * @property ?string $footer_text
 * @property ?array<int, mixed> $buttons
 * @property ?array<int, mixed> $components
 * @property ?string $rejection_reason
 * @property ?Carbon $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'uuid',
        'whatsapp_account_id',
        'meta_template_id',
        'name',
        'category',
        'language',
        'status',
        'header_type',
        'header_content',
        'body_text',
        'footer_text',
        'buttons',
        'components',
        'rejection_reason',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppTemplateStatus::class,
            'buttons' => 'array',
            'components' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Account this template is registered under (if bound to a specific account).
     *
     * @return BelongsTo<WhatsAppAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /**
     * Render the template body text with positional parameters ({{1}}, {{2}}, etc.).
     *
     * @param  array<int|string, string>  $variables
     */
    public function render(array $variables = []): string
    {
        $rendered = $this->body_text;

        foreach ($variables as $index => $value) {
            // Handle both 1-based and 0-based indexing
            $placeholderNumber = is_numeric($index) ? ((int) $index + 1) : $index;
            $placeholder = '{{'.$placeholderNumber.'}}';
            $rendered = str_replace($placeholder, (string) $value, $rendered);

            // Also check direct key if string
            if (is_string($index)) {
                $rendered = str_replace('{{'.$index.'}}', (string) $value, $rendered);
            }
        }

        return $rendered;
    }

    /**
     * Total number of positional parameters in body text (e.g. {{1}}, {{2}}).
     */
    public function getVariableCountAttribute(): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $this->body_text, $matches);

        return ! empty($matches[1]) ? count(array_unique($matches[1])) : 0;
    }

    /**
     * Scope: Only Meta-approved templates.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', WhatsAppTemplateStatus::Approved);
    }

    /**
     * Scope: Filter by category (e.g. MARKETING, UTILITY).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', strtoupper($category));
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
