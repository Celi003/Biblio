<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Fine;
use App\Models\LoanRequest;
use App\Models\LoanRequestEvent;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class LoanRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/api/loan-requests',
        tags: ['Loan Requests'],
        summary: 'List my loan requests',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index()
    {
        $user = auth('api')->user();

        $perPage = (int) request()->query('per_page', 20);
        if ($perPage < 1) {
            $perPage = 20;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = LoanRequest::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->orderByDesc('requested_at');

        if (request()->boolean('active')) {
            $query->where('is_active', true);
        }

        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/loan-requests',
        tags: ['Loan Requests'],
        summary: 'Create a loan request',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['book_id', 'due_at'])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        $user = auth('api')->user();

        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'note' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
        ]);

        $dueAt = new \DateTimeImmutable($data['due_at']);
        $today = new \DateTimeImmutable('today');
        $maxDays = max(1, Setting::getInt('loan.max_days', 30));
        $maxDue = $today->add(new \DateInterval('P' . $maxDays . 'D'));

        if ($dueAt < $today) {
            return response()->json(['message' => 'Return date must be today or later.'], 422);
        }

        if ($dueAt > $maxDue) {
            return response()->json(['message' => 'Return date cannot exceed ' . $maxDays . ' days.'], 422);
        }

        // Sanctions: block new loans if user is overdue or has unpaid fines.
        $graceDays = max(0, Setting::getInt('loan.grace_days', 0));
        $blockOnOverdue = Setting::getBool('loan.block_on_overdue', true);
        $blockOnUnpaidFines = Setting::getBool('loan.block_on_unpaid_fines', true);
        $maxUnpaidFinesCents = max(0, Setting::getInt('loan.max_unpaid_fines_cents', 0));

        if ($blockOnOverdue) {
            $today = new \DateTimeImmutable('today');
            $cutoff = $today->sub(new \DateInterval('P' . $graceDays . 'D'));

            $hasOverdue = LoanRequest::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED])
                ->whereNotNull('due_at')
                ->where('due_at', '<', $cutoff)
                ->exists();

            if ($hasOverdue) {
                return response()->json([
                    'message' => 'You have overdue loans. New loan requests are blocked until the situation is resolved.',
                ], 409);
            }
        }

        if ($blockOnUnpaidFines) {
            $unpaid = (int) Fine::query()
                ->where('user_id', $user->id)
                ->where('status', Fine::STATUS_UNPAID)
                ->sum('amount_cents');

            $blocked = $maxUnpaidFinesCents === 0 ? ($unpaid > 0) : ($unpaid > $maxUnpaidFinesCents);
            if ($blocked) {
                return response()->json([
                    'message' => 'You have unpaid fines. New loan requests are blocked until payment.',
                ], 409);
            }
        }

        try {
            $created = DB::transaction(function () use ($user, $data, $dueAt) {
                $book = Book::lockForUpdate()->findOrFail($data['book_id']);

                $alreadyHasActiveRequest = LoanRequest::query()
                    ->where('user_id', $user->id)
                    ->where('book_id', $book->id)
                    ->where('is_active', true)
                    ->exists();

                if ($alreadyHasActiveRequest) {
                    abort(response()->json([
                        'message' => 'You already have an active loan request for this book.',
                    ], 409));
                }

                if ($book->available_copies < 1) {
                    abort(response()->json([
                        'message' => 'This book is currently on loan.',
                    ], 409));
                }

                $loan = LoanRequest::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'status' => LoanRequest::STATUS_PENDING,
                    'is_active' => true,
                    'note' => $data['note'] ?? null,
                    'requested_at' => new \DateTimeImmutable('now'),
                    'due_at' => $dueAt,
                ])->load('book');

                LoanRequestEvent::record($loan->id, $user->id, 'created', [
                    'due_at' => $loan->due_at?->format(DATE_ATOM),
                ]);

                return $loan;
            });

            return response()->json($created, 201);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'loan_requests_one_active_per_user_book')) {
                return response()->json([
                    'message' => 'You already have an active loan request for this book.',
                ], 409);
            }

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/loan-requests/{id}',
        tags: ['Loan Requests'],
        summary: 'Get one of my loan requests',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id)
    {
        $user = auth('api')->user();
        $loanRequest = LoanRequest::query()
            ->with('book')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json($loanRequest);
    }

    #[OA\Post(
        path: '/api/loan-requests/{id}/request-return',
        tags: ['Loan Requests'],
        summary: 'Request a return for an approved loan',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 409, description: 'Invalid status'),
        ]
    )]
    public function requestReturn(string $id)
    {
        $user = auth('api')->user();

        $loanRequest = LoanRequest::query()
            ->where('user_id', $user->id)
            ->with('book')
            ->findOrFail($id);

        if ($loanRequest->status !== LoanRequest::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only approved loans can be returned.',
            ], 409);
        }

        $loanRequest->status = LoanRequest::STATUS_RETURN_REQUESTED;
        $loanRequest->is_active = true;
        $loanRequest->save();

        LoanRequestEvent::record($loanRequest->id, $user->id, 'return_requested');

        return response()->json($loanRequest->fresh()->load('book'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return response()->json(['message' => 'Not supported.'], 405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json(['message' => 'Not supported.'], 405);
    }
}
