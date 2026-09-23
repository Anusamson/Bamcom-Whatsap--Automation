<?php

namespace App\Models;

use App\Enums\AutomationConditionOperator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Automation Condition Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $workflow_id
 * @property string $field
 * @property AutomationConditionOperator|string $operator
 * @property mixed $value
 * @property string $logical_operator
 * @property int $sort_order
 */
class AutomationCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workflow_id',
        'field',
        'operator',
        'value',
        'logical_operator',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'operator' => AutomationConditionOperator::class,
            'value' => 'json',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $condition): void {
            if (empty($condition->uuid)) {
                $condition->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }
}
