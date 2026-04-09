<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FineController extends Controller
{
    #[OA\Get(
        path: '/api/admin/fines',
        tags: ['Admin: Fines'],
        summary: 'List fines',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $query = Fine::query()->with(['user', 'loanRequest.book'])->orderByDesc('calculated_at');

        $status = request()->string('status')->trim()->toString();
        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    #[OA\Put(
        path: '/api/admin/fines/{id}',
        tags: ['Admin: Fines'],
        summary: 'Update fine status',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'])),
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(Request $request, string $id)
    {
        $fine = Fine::query()->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', [Fine::STATUS_UNPAID, Fine::STATUS_PAID, Fine::STATUS_WAIVED])],
        ]);

        $fine->status = $data['status'];
        if ($fine->status === Fine::STATUS_PAID) {
            $fine->paid_at = new \DateTimeImmutable('now');
        }
        if ($fine->status !== Fine::STATUS_PAID) {
            $fine->paid_at = null;
        }

        $fine->save();

        return response()->json($fine->fresh()->load(['user', 'loanRequest.book']));
    }
}
