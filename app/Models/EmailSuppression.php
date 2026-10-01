<?php

namespace App\Models;

use App\Enums\EmailSuppressionReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Suppressed Email Address Model.
 *
 * @property int $id
 * @property string $email
 * @property ?int $contact_id
 * @property EmailSuppressionReason $reason
 * @property ?string $details
 * @property Carbon $suppressed_at
 * @property ?int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EmailSuppression extends Model
{
    use HasFactory;

    protected $table = 'email_suppressions';

    protected $fillable = [
        'email',
        'contact_id',
        'reason',
        'details',
        'suppressed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reason' => EmailSuppressionReason::class,
            'suppressed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', strtolower(trim($email)));
    }
}
