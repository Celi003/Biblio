<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'description',
        'published_at',
        'total_copies',
        'available_copies',
    ];

    protected $casts = [
        'published_at' => 'date',
        'total_copies' => 'integer',
        'available_copies' => 'integer',
    ];

    public function loanRequests(): HasMany
    {
        return $this->hasMany(LoanRequest::class);
    }
}
