<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fine extends Model
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'user_id',
        'loan_request_id',
        'amount_cents',
        'days_overdue',
        'status',
        'calculated_at',
        'paid_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'paid_at' => 'datetime',
        'amount_cents' => 'integer',
        'days_overdue' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loanRequest(): BelongsTo
    {
        return $this->belongsTo(LoanRequest::class);
    }
}
