<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * CRM Sales & Follow-up Task Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property ?string $description
 * @property ?int $contact_id
 * @property ?int $lead_id
 * @property ?int $deal_id
 * @property ?int $assigned_user_id
 * @property ?int $created_by_id
 * @property Carbon $due_at
 * @property ?Carbon $completed_at
 * @property TaskPriority $priority
 * @property TaskStatus $status
 * @property TaskType $type
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read ?Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?Deal $deal
 * @property-read ?User $assignedUser
 * @property-read ?User $creator
 * @property-read bool $is_overdue
 * @property-read bool $is_due_today
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'contact_id',
        'lead_id',
        'deal_id',
        'assigned_user_id',
        'created_by_id',
        'due_at',
        'completed_at',
        'priority',
        'status',
        'type',
    ];

    protected $appends = [
        'is_overdue',
        'is_due_today',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'type' => TaskType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The contact associated with this task.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The sales lead associated with this task.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The deal associated with this task.
     *
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * The sales representative or user assigned to complete this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * The user who created the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Whether the task is overdue.
     */
    protected function isOverdue(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->status === TaskStatus::Completed || $this->status === TaskStatus::Cancelled) {
                    return false;
                }

                return $this->due_at ? $this->due_at->isPast() : false;
            }
        );
    }

    /**
     * Whether the task is due today.
     */
    protected function isDueToday(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if ($this->status === TaskStatus::Completed || $this->status === TaskStatus::Cancelled) {
                    return false;
                }

                return $this->due_at ? $this->due_at->isToday() : false;
            }
        );
    }

    /**
     * Scope for tasks due today that are still pending / in progress.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('due_at', today())
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
    }

    /**
     * Scope for overdue tasks.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_at', '<', now())
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
    }

    /**
     * Scope for upcoming tasks (due after today).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('due_at', '>', now()->endOfDay())
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
    }

    /**
     * Scope for completed tasks.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::Completed->value);
    }

    /**
     * Filter by assigned user.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Filter by priority.
     */
    public function scopePriority(Builder $query, TaskPriority|string $priority): Builder
    {
        $value = $priority instanceof TaskPriority ? $priority->value : $priority;

        return $query->where('priority', $value);
    }

    /**
     * Filter by task type.
     */
    public function scopeType(Builder $query, TaskType|string $type): Builder
    {
        $value = $type instanceof TaskType ? $type->value : $type;

        return $query->where('type', $value);
    }

    /**
     * Search scope across task title and description.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }
}
