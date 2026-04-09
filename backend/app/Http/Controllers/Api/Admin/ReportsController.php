<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\LoanRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReportsController extends Controller
{
    #[OA\Get(
        path: '/api/admin/reports/overview',
        tags: ['Admin: Reports'],
        summary: 'Advanced reporting overview',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function overview()
    {
        $today = new \DateTimeImmutable('today');
        $graceDays = max(0, Setting::getInt('loan.grace_days', 0));
        $graceCutoff = $today->sub(new \DateInterval('P' . $graceDays . 'D'));

        $dueSoonDays = max(0, Setting::getInt('reminder.due_soon_days_before', 3));
        $dueSoonCutoff = $today->add(new \DateInterval('P' . $dueSoonDays . 'D'));

        $activeStatuses = [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED];

        $overdueLoans = LoanRequest::query()
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $graceCutoff)
            ->count();

        $dueSoonLoans = LoanRequest::query()
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$today, $dueSoonCutoff])
            ->count();

        $unpaidTotal = (int) Fine::query()->where('status', Fine::STATUS_UNPAID)->sum('amount_cents');
        $paidTotal = (int) Fine::query()->where('status', Fine::STATUS_PAID)->sum('amount_cents');

        $topUnpaidUsers = DB::table('fines')
            ->select('user_id', DB::raw('SUM(amount_cents) as total_unpaid_cents'))
            ->where('status', Fine::STATUS_UNPAID)
            ->groupBy('user_id')
            ->orderByDesc('total_unpaid_cents')
            ->limit(5)
            ->get();

        return response()->json([
            'overdue_loans_count' => $overdueLoans,
            'due_soon_loans_count' => $dueSoonLoans,
            'fines_unpaid_total_cents' => $unpaidTotal,
            'fines_paid_total_cents' => $paidTotal,
            'top_unpaid_users' => $topUnpaidUsers,
        ]);
    }
}
