<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanRequestEvent;
use OpenApi\Attributes as OA;

class HistoryController extends Controller
{
    #[OA\Get(
        path: '/api/history',
        tags: ['History'],
        summary: 'List my recent loan events',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index()
    {
        $user = auth('api')->user();

        $events = LoanRequestEvent::query()
            ->with(['loanRequest.book'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($events);
    }
}
