<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\LoanRequest;
use App\Models\User;
use OpenApi\Attributes as OA;

class StatsController extends Controller
{
    #[OA\Get(
        path: '/api/admin/stats',
        tags: ['Admin: Stats'],
        summary: 'Get detailed stats',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        return response()->json([
            'books_count' => Book::query()->count(),
            'users_count' => User::query()->count(),
            'loan_requests_pending_count' => LoanRequest::query()->where('status', LoanRequest::STATUS_PENDING)->count(),
            'books_borrowed_count' => LoanRequest::query()->whereIn('status', [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED])->count(),
        ]);
    }
}
