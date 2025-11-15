<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentReminder;
use App\Models\Setting;
use App\Models\Student;
use App\Models\WhatsappLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderService
{
    public function scheduleOverdueReminders(?Carbon $runDate = null): int
    {
        $today = ($runDate ?? now())->copy()->startOfDay();

        $cadence = (int) Setting::getValue('reminder.cadence_days', config('fees.penalty.reminder_frequency_days'));
        $cadence = max($cadence, 1);

        $installments = Installment::with(['fee.student'])
            ->whereColumn('paid_amount', '<', 'amount')
            ->whereDate('due_date', '<=', $today)
            ->get();

        $scheduled = 0;

        foreach ($installments as $installment) {
            if ($this->shouldSkipReminder($installment, $today, $cadence)) {
                continue;
            }

            $student = $installment->fee->student;

            DB::transaction(function () use ($installment, $student, $today, &$scheduled) {
                $reminder = InstallmentReminder::create([
                    'student_id' => $student->id,
                    'installment_id' => $installment->id,
                    'channel' => 'whatsapp',
                    'reminder_type' => 'overdue',
                    'scheduled_for' => $today->toDateString(),
                    'status' => 'queued',
                    'payload' => [
                        'installment_number' => $installment->installment_number,
                        'due_date' => $installment->due_date->format('Y-m-d'),
                        'outstanding_amount' => round($installment->amount - $installment->paid_amount, 2),
                    ],
                ]);

                $this->logWhatsappReminder($student, $installment, $reminder);

                $scheduled++;
            });
        }

        return $scheduled;
    }

    private function shouldSkipReminder(Installment $installment, Carbon $today, int $cadence): bool
    {
        $outstanding = round($installment->amount - $installment->paid_amount, 2);

        if ($outstanding <= 0) {
            return true;
        }

        if ($installment->reminders()
            ->where('reminder_type', 'overdue')
            ->whereDate('scheduled_for', $today)
            ->exists()) {
            return true;
        }

        $lastReminder = $installment->reminders()
            ->where('reminder_type', 'overdue')
            ->orderByDesc('scheduled_for')
            ->first();

        if (! $lastReminder) {
            return false;
        }

        return $lastReminder->scheduled_for->diffInDays($today) < $cadence;
    }

    private function logWhatsappReminder(Student $student, Installment $installment, InstallmentReminder $reminder): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = sprintf(
            'Reminder: Installment #%s of ₹%s was due on %s. Please clear the outstanding amount at the earliest.',
            $installment->installment_number,
            number_format($installment->amount, 2),
            $installment->due_date->format('d M Y')
        );

        WhatsappLog::create([
            'student_id' => $student->id,
            'installment_id' => $installment->id,
            'message_type' => 'installment_reminder',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
            'response_data' => null,
            'sent_at' => null,
        ]);
    }
}


