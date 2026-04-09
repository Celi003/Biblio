<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LoanRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURN_REQUESTED = 'return_requested';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RETURNED = 'returned';

    public static function activeStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_RETURN_REQUESTED];
    }

    protected $fillable = [
        'user_id',
        'book_id',
        'status',
        'is_active',
        'note',
        'requested_at',
        'decided_at',
        'due_at',
        'returned_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LoanRequestEvent::class);
    }

    public function fine(): HasOne
    {
        return $this->hasOne(Fine::class);
    }
}
