<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reusable Email Template Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $subject
 * @property string $body_html
 * @property ?string $body_plain
 * @property string $category
 * @property ?list<string> $variables
 * @property bool $is_active
 * @property ?int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_templates';

    protected $fillable = [
        'uuid',
        'name',
        'subject',
        'body_html',
        'body_plain',
        'category',
        'variables',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailTemplate $template): void {
            if (empty($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Scope to active templates.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * User who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Outbound messages created from this template.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }
}
