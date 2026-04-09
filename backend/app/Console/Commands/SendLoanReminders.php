<?php

namespace App\Console\Commands;

use App\Models\LoanRequest;
use App\Models\Reminder;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLoanReminders extends Command
{
    protected $signature = 'biblio:send-reminders';

    protected $description = 'Send due-soon and overdue reminders (email) and store them in DB.';

    public function handle(): int
    {
        $today = new \DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');

        $dueSoonDaysBefore = max(0, Setting::getInt('reminder.due_soon_days_before', 3));
        $overdueFrequencyDays = max(1, Setting::getInt('reminder.overdue_frequency_days', 7));
        $graceDays = max(0, Setting::getInt('loan.grace_days', 0));

        $activeStatuses = [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_RETURN_REQUESTED];

        // Due soon reminders (send today for loans due in N days)
        $targetDue = $today->add(new \DateInterval('P' . $dueSoonDaysBefore . 'D'));
        $targetDueStr = $targetDue->format('Y-m-d');

        $dueSoonLoans = LoanRequest::query()
            ->with(['user', 'book'])
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_at')
            ->whereDate('due_at', '=', $targetDueStr)
            ->get();

        $sent = 0;

        foreach ($dueSoonLoans as $loan) {
            $reminder = Reminder::query()->firstOrCreate([
                'loan_request_id' => $loan->id,
                'user_id' => $loan->user_id,
                'type' => Reminder::TYPE_DUE_SOON,
                'scheduled_for' => $todayStr,
            ]);

            if ($reminder->status !== Reminder::STATUS_PENDING) {
                continue;
            }

            $subject = 'Rappel: retour prévu bientôt';
            $body = "Bonjour {$loan->user->name},\n\n" .
                "Rappel: le retour du livre \"{$loan->book->title}\" est prévu le {$targetDueStr}.\n" .
                "Merci de respecter la date de retour.\n\n" .
                "Biblio";

            $this->sendReminder($reminder, $loan->user->email, $subject, $body);
            $sent += 1;
        }

        // Overdue reminders (send every X days after grace period)
        $overdueLoans = LoanRequest::query()
            ->with(['user', 'book'])
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $today)
            ->get();

        foreach ($overdueLoans as $loan) {
            $dueAtDate = new \DateTimeImmutable($loan->due_at->format('Y-m-d'));
            $overdueStart = $dueAtDate->add(new \DateInterval('P' . $graceDays . 'D'));
            if ($overdueStart >= $today) {
                continue;
            }

            $daysOverdue = (int) $overdueStart->diff($today)->days;
            if ($daysOverdue % $overdueFrequencyDays !== 0) {
                continue;
            }

            $reminder = Reminder::query()->firstOrCreate([
                'loan_request_id' => $loan->id,
                'user_id' => $loan->user_id,
                'type' => Reminder::TYPE_OVERDUE,
                'scheduled_for' => $todayStr,
            ]);

            if ($reminder->status !== Reminder::STATUS_PENDING) {
                continue;
            }

            $subject = 'Relance: retour en retard';
            $body = "Bonjour {$loan->user->name},\n\n" .
                "Le retour du livre \"{$loan->book->title}\" est en retard (date prévue: {$dueAtDate->format('Y-m-d')}).\n" .
                "Merci de procéder au retour au plus vite. Des sanctions/amendes peuvent s’appliquer.\n\n" .
                "Biblio";

            $this->sendReminder($reminder, $loan->user->email, $subject, $body);
            $sent += 1;
        }

        $this->info('Reminders processed (attempted sends): ' . $sent);

        return self::SUCCESS;
    }

    private function sendReminder(Reminder $reminder, string $email, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            $reminder->status = Reminder::STATUS_SENT;
            $reminder->sent_at = now();
            $reminder->error = null;
            $reminder->save();
        } catch (\Throwable $e) {
            $reminder->status = Reminder::STATUS_FAILED;
            $reminder->error = $e->getMessage();
            $reminder->save();

            $this->warn('Failed to send to ' . $email . ': ' . $e->getMessage());
        }
    }
}
