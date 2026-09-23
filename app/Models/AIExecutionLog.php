<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit log recording AI model executions, prompt metadata, tool calls, and results.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $conversation_id
 * @property ?int $contact_id
 * @property ?int $user_id
 * @property string $agent_name
 * @property string $provider
 * @property ?string $model
 * @property string $user_message
 * @property ?string $system_prompt
 * @property ?array<int, array<string, mixed>> $tool_calls
 * @property ?array<string, mixed> $tool_results
 * @property ?string $response_content
 * @property ?string $finish_reason
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $duration_ms
 * @property string $status
 * @property ?string $error_message
 * @property ?array<string, mixed> $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?Conversation $conversation
 * @property-read ?Contact $contact
 * @property-read ?User $user
 */
class AIExecutionLog extends Model
{
    use HasFactory;

    protected $table = 'ai_execution_logs';

    protected $fillable = [
        'uuid',
        'conversation_id',
        'contact_id',
        'user_id',
        'agent_name',
        'provider',
        'model',
        'user_message',
        'system_prompt',
        'tool_calls',
        'tool_results',
        'response_content',
        'finish_reason',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'duration_ms',
        'status',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'metadata' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'duration_ms' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    public function scopeWithToolCalls(Builder $query): Builder
    {
        return $query->whereNotNull('tool_calls');
    }

    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }
}
