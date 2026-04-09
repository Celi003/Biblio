<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestEvent extends Model
{
    protected $fillable = [
        'loan_request_id',
        'user_id',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(int $loanRequestId, ?int $userId, string $type, array $meta = []): self
    {
        return self::query()->create([
            'loan_request_id' => $loanRequestId,
            'user_id' => $userId,
            'type' => $type,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
