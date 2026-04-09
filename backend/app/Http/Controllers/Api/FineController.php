<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use OpenApi\Attributes as OA;

class FineController extends Controller
{
    #[OA\Get(
        path: '/api/fines',
        tags: ['Fines'],
        summary: 'List my fines',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index()
    {
        $user = auth('api')->user();

        $fines = Fine::query()
            ->with(['loanRequest.book'])
            ->where('user_id', $user->id)
            ->orderByDesc('calculated_at')
            ->paginate(20);

        return response()->json($fines);
    }
}
