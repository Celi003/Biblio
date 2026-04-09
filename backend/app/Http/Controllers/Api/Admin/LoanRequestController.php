<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
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
        path: '/api/admin/loan-requests',
        tags: ['Admin: Loan Requests'],
        summary: 'List loan requests',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $query = LoanRequest::query()
            ->with(['user', 'book'])
            ->orderByDesc('requested_at');

        $status = request()->string('status')->trim()->toString();
        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    #[OA\Post(
        path: '/api/admin/loan-requests',
        tags: ['Admin: Loan Requests'],
        summary: 'Create loan request',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['user_id','book_id','due_at'])),
        responses: [new OA\Response(response: 201, description: 'Created')]
    )]
    public function store(Request $request)
    {
        $admin = auth('api')->user();

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
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

        try {
            $created = DB::transaction(function () use ($data, $dueAt, $admin) {
                $book = Book::lockForUpdate()->findOrFail($data['book_id']);

                $alreadyHasActiveRequest = LoanRequest::query()
                    ->where('user_id', $data['user_id'])
                    ->where('book_id', $book->id)
                    ->where('is_active', true)
                    ->exists();

                if ($alreadyHasActiveRequest) {
                    abort(response()->json([
                        'message' => 'User already has an active loan request for this book.',
                    ], 409));
                }

                if ($book->available_copies < 1) {
                    abort(response()->json([
                        'message' => 'This book is currently on loan.',
                    ], 409));
                }

                $loan = LoanRequest::create([
                    'user_id' => $data['user_id'],
                    'book_id' => $book->id,
                    'status' => LoanRequest::STATUS_PENDING,
                    'is_active' => true,
                    'note' => $data['note'] ?? null,
                    'requested_at' => new \DateTimeImmutable('now'),
                    'due_at' => $dueAt,
                ])->load(['user', 'book']);

                LoanRequestEvent::record($loan->id, $admin?->id, 'admin_created', [
                    'for_user_id' => $loan->user_id,
                    'due_at' => $loan->due_at?->format(DATE_ATOM),
                ]);

                return $loan;
            });

            return response()->json($created, 201);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'loan_requests_one_active_per_user_book')) {
                return response()->json([
                    'message' => 'User already has an active loan request for this book.',
                ], 409);
            }

            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    #[OA\Get(
        path: '/api/admin/loan-requests/{id}',
        tags: ['Admin: Loan Requests'],
        summary: 'Get loan request',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(string $id)
    {
        return response()->json(LoanRequest::with(['user', 'book'])->findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/api/admin/loan-requests/{id}',
        tags: ['Admin: Loan Requests'],
        summary: 'Update loan request status',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['status'])),
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(Request $request, string $id)
    {
        $admin = auth('api')->user();
        $loanRequest = LoanRequest::with('book')->findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', [
                LoanRequest::STATUS_PENDING,
                LoanRequest::STATUS_APPROVED,
                LoanRequest::STATUS_RETURN_REQUESTED,
                LoanRequest::STATUS_REJECTED,
                LoanRequest::STATUS_RETURNED,
            ])],
            'due_at' => ['nullable', 'date'],
        ]);

        $newStatus = $data['status'];
        $oldStatus = $loanRequest->status;

        // Enforce a strict workflow to avoid stock drift:
        // - pending -> approved | rejected
        // - approved -> return_requested | returned
        // - return_requested -> returned
        // - rejected/returned -> no further transitions
        $allowedTransitions = match ($oldStatus) {
            LoanRequest::STATUS_PENDING => [
                LoanRequest::STATUS_PENDING,
                LoanRequest::STATUS_APPROVED,
                LoanRequest::STATUS_REJECTED,
            ],
            LoanRequest::STATUS_APPROVED => [
                LoanRequest::STATUS_APPROVED,
                LoanRequest::STATUS_RETURN_REQUESTED,
                LoanRequest::STATUS_RETURNED,
            ],
            LoanRequest::STATUS_RETURN_REQUESTED => [
                LoanRequest::STATUS_RETURN_REQUESTED,
                LoanRequest::STATUS_RETURNED,
            ],
            LoanRequest::STATUS_REJECTED => [LoanRequest::STATUS_REJECTED],
            LoanRequest::STATUS_RETURNED => [LoanRequest::STATUS_RETURNED],
            default => [$oldStatus],
        };

        if (!in_array($newStatus, $allowedTransitions, true)) {
            return response()->json([
                'message' => 'Invalid status transition: ' . $oldStatus . ' -> ' . $newStatus,
            ], 422);
        }

        if (isset($data['due_at'])) {
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
        }

        return DB::transaction(function () use ($loanRequest, $newStatus, $oldStatus, $data, $admin) {
            $book = Book::lockForUpdate()->findOrFail($loanRequest->book_id);

            if ($oldStatus === LoanRequest::STATUS_PENDING && $newStatus === LoanRequest::STATUS_APPROVED) {
                if ($book->available_copies < 1) {
                    return response()->json(['message' => 'No available copies to approve.'], 422);
                }

                $book->available_copies -= 1;
                $book->save();

                $loanRequest->decided_at = new \DateTimeImmutable('now');
                if (isset($data['due_at'])) {
                    $loanRequest->due_at = new \DateTimeImmutable($data['due_at']);
                } elseif ($loanRequest->due_at === null) {
                    $loanRequest->due_at = (new \DateTimeImmutable('today'))->add(new \DateInterval('P14D'));
                }
            }

            if ($oldStatus === LoanRequest::STATUS_PENDING && $newStatus === LoanRequest::STATUS_REJECTED) {
                $loanRequest->decided_at = new \DateTimeImmutable('now');
            }

            if ($newStatus === LoanRequest::STATUS_RETURN_REQUESTED && $oldStatus !== LoanRequest::STATUS_APPROVED) {
                return response()->json(['message' => 'Return request is only valid for approved loans.'], 422);
            }

            if ($newStatus === LoanRequest::STATUS_RETURNED && !in_array($oldStatus, [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED], true)) {
                return response()->json(['message' => 'Only approved loans can be marked as returned.'], 422);
            }

            if (in_array($oldStatus, [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED], true) && $newStatus === LoanRequest::STATUS_RETURNED) {
                // Clamp to total_copies for safety.
                if ($book->available_copies < $book->total_copies) {
                    $book->available_copies += 1;
                }
                $book->save();

                $loanRequest->returned_at = new \DateTimeImmutable('now');
            }

            $loanRequest->status = $newStatus;
            $loanRequest->is_active = in_array($newStatus, LoanRequest::activeStatuses(), true) ? true : null;
            $loanRequest->save();

            if ($oldStatus !== $newStatus) {
                LoanRequestEvent::record($loanRequest->id, $admin?->id, 'status_changed', [
                    'from' => $oldStatus,
                    'to' => $newStatus,
                ]);
            }

            return response()->json($loanRequest->fresh()->load(['user', 'book']));
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/api/admin/loan-requests/{id}',
        tags: ['Admin: Loan Requests'],
        summary: 'Delete loan request',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function destroy(string $id)
    {
        $loanRequest = LoanRequest::findOrFail($id);
        $loanRequest->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
