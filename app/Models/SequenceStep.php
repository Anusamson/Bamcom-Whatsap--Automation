<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Sequence Step Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $sequence_id
 * @property int $step_number
 * @property ?string $name
 * @property int $delay_minutes
 * @property string $delay_type
 * @property ?array<string, mixed> $whatsapp_config
 * @property ?array<string, mixed> $task_config
 * @property ?array<string, mixed> $stage_change_config
 * @property ?array<string, mixed> $tag_config
 * @property ?array<string, mixed> $assignment_config
 * @property ?array<string, mixed> $notification_config
 * @property ?array<string, mixed> $applicability_rules
 */
class SequenceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sequence_id',
        'step_number',
        'name',
        'delay_minutes',
        'delay_type',
        'whatsapp_config',
        'task_config',
        'stage_change_config',
        'tag_config',
        'assignment_config',
        'notification_config',
        'applicability_rules',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'delay_minutes' => 'integer',
            'whatsapp_config' => 'array',
            'task_config' => 'array',
            'stage_change_config' => 'array',
            'tag_config' => 'array',
            'assignment_config' => 'array',
            'notification_config' => 'array',
            'applicability_rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $step): void {
            if (empty($step->uuid)) {
                $step->uuid = (string) Str::uuid();
            }
        });
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'sequence_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SequenceStepLog::class, 'sequence_step_id');
    }

    public function hasWhatsApp(): bool
    {
        return ! empty($this->whatsapp_config) && (! empty($this->whatsapp_config['message']) || ! empty($this->whatsapp_config['template_name']));
    }

    public function hasTask(): bool
    {
        return ! empty($this->task_config) && ! empty($this->task_config['title']);
    }

    public function hasStageChange(): bool
    {
        return ! empty($this->stage_change_config) && (! empty($this->stage_change_config['stage_id']) || ! empty($this->stage_change_config['stage_name']));
    }

    public function hasTagChange(): bool
    {
        return ! empty($this->tag_config) && (! empty($this->tag_config['add_tags']) || ! empty($this->tag_config['remove_tags']) || ! empty($this->tag_config['tag']));
    }

    public function hasAssignment(): bool
    {
        return ! empty($this->assignment_config) && (! empty($this->assignment_config['user_id']) || ! empty($this->assignment_config['agent_id']) || ! empty($this->assignment_config['round_robin']));
    }

    public function hasNotification(): bool
    {
        return ! empty($this->notification_config) && ! empty($this->notification_config['message']);
    }
}
