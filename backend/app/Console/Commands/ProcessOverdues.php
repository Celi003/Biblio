<?php

namespace App\Console\Commands;

use App\Models\Fine;
use App\Models\LoanRequest;
use App\Models\LoanRequestEvent;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdues extends Command
{
    protected $signature = 'biblio:process-overdues';

    protected $description = 'Compute/update fines for overdue loans.';

    public function handle(): int
    {
        $today = new \DateTimeImmutable('today');

        $graceDays = max(0, Setting::getInt('loan.grace_days', 0));
        $perDayCents = max(0, Setting::getInt('fine.per_day_cents', 100));
        $capCents = max(0, Setting::getInt('fine.cap_cents', 3000));

        $cutoff = $today->sub(new \DateInterval('P' . $graceDays . 'D'));

        $loans = LoanRequest::query()
            ->whereIn('status', [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED])
            ->whereNotNull('due_at')
            ->where('due_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($loans as $loan) {
            $dueAt = new \DateTimeImmutable($loan->due_at->format('Y-m-d'));
            $overdueStart = $dueAt->add(new \DateInterval('P' . $graceDays . 'D'));

            if ($overdueStart >= $today) {
                continue;
            }

            $daysOverdue = (int) $overdueStart->diff($today)->days;
            $amount = $capCents > 0 ? min($daysOverdue * $perDayCents, $capCents) : ($daysOverdue * $perDayCents);

            DB::transaction(function () use ($loan, $daysOverdue, $amount, $today, &$count) {
                /** @var Fine|null $fine */
                $fine = Fine::query()->where('loan_request_id', $loan->id)->lockForUpdate()->first();

                if ($fine && $fine->status !== Fine::STATUS_UNPAID) {
                    return;
                }

                if (!$fine) {
                    $fine = Fine::query()->create([
                        'user_id' => $loan->user_id,
                        'loan_request_id' => $loan->id,
                        'amount_cents' => $amount,
                        'days_overdue' => $daysOverdue,
                        'status' => Fine::STATUS_UNPAID,
                        'calculated_at' => $today,
                    ]);

                    LoanRequestEvent::record($loan->id, null, 'fine_created', [
                        'amount_cents' => $amount,
                        'days_overdue' => $daysOverdue,
                    ]);
                } else {
                    if ((int) $fine->amount_cents !== $amount || (int) $fine->days_overdue !== $daysOverdue) {
                        $fine->amount_cents = $amount;
                        $fine->days_overdue = $daysOverdue;
                        $fine->calculated_at = $today;
                        $fine->save();

                        LoanRequestEvent::record($loan->id, null, 'fine_updated', [
                            'amount_cents' => $amount,
                            'days_overdue' => $daysOverdue,
                        ]);
                    }
                }

                $count += 1;
            });
        }

        $this->info('Processed overdue loans: ' . $count);

        return self::SUCCESS;
    }
}
