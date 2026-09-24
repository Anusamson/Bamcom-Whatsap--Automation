<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * AI Sales Intelligence & Executive CRM Insights Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $period
 * @property ?Carbon $date_from
 * @property ?Carbon $date_to
 * @property string $executive_summary
 * @property array<string, mixed> $metrics_snapshot
 * @property array<string, mixed> $insights
 * @property ?array<int, string|array<string, mixed>> $recommendations
 * @property ?int $generated_by_user_id
 * @property string $model_used
 * @property int $tokens_used
 * @property float $duration_ms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?User $generatedByUser
 */
class AiSalesInsight extends Model
{
    use HasFactory;

    protected $table = 'ai_sales_insights';

    protected $fillable = [
        'uuid',
        'title',
        'period',
        'date_from',
        'date_to',
        'executive_summary',
        'metrics_snapshot',
        'insights',
        'recommendations',
        'generated_by_user_id',
        'model_used',
        'tokens_used',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'metrics_snapshot' => 'array',
            'insights' => 'array',
            'recommendations' => 'array',
            'date_from' => 'date',
            'date_to' => 'date',
            'tokens_used' => 'integer',
            'duration_ms' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $insight): void {
            if (empty($insight->uuid)) {
                $insight->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * User / Agent who initiated this AI sales intelligence synthesis.
     *
     * @return BelongsTo<User, $this>
     */
    public function generatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
