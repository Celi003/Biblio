<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanRequestEvent;
use OpenApi\Attributes as OA;

class HistoryController extends Controller
{
    #[OA\Get(
        path: '/api/admin/history',
        tags: ['Admin: History'],
        summary: 'List recent loan events',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $events = LoanRequestEvent::query()
            ->with(['user', 'loanRequest.book'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($events);
    }
}
