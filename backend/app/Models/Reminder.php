<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    public const TYPE_DUE_SOON = 'due_soon';
    public const TYPE_OVERDUE = 'overdue';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'loan_request_id',
        'user_id',
        'type',
        'scheduled_for',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'scheduled_for' => 'date',
        'sent_at' => 'datetime',
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
