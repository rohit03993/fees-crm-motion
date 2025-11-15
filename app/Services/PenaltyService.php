<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Penalty;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenaltyService
{
    public function applyPenalties(?Carbon $runDate = null): int
    {
        $today = ($runDate ?? now())->copy()->startOfDay();

        $graceDays = (int) Setting::getValue('penalty.grace_days', config('fees.penalty.grace_days'));
        $rate = (float) Setting::getValue('penalty.rate_percent_per_day', config('fees.penalty.rate_percent_per_day'));

        if ($rate <= 0) {
            return 0;
        }

        $cutoffDate = $today->copy()->subDays($graceDays);

        $installments = Installment::with(['fee.student'])
            ->whereDate('due_date', '<=', $cutoffDate)
            ->whereColumn('paid_amount', '<', 'amount')
            ->get();

        $created = 0;

        foreach ($installments as $installment) {
            $daysLate = $this->calculateDaysLate($installment->due_date, $today, $graceDays);

            if ($daysLate <= 0) {
                continue;
            }

            if ($installment->penalties()->whereDate('applied_date', $today)->exists()) {
                continue;
            }

            $outstanding = round($installment->amount - $installment->paid_amount, 2);

            if ($outstanding <= 0) {
                continue;
            }

            $penaltyAmount = round($outstanding * ($rate / 100) * $daysLate, 2);

            if ($penaltyAmount <= 0) {
                continue;
            }

            DB::transaction(function () use ($installment, $today, $daysLate, $rate, $penaltyAmount) {
                Penalty::create([
                    'student_id' => $installment->fee->student_id,
                    'installment_id' => $installment->id,
                    'penalty_type' => 'auto',
                    'days_delayed' => $daysLate,
                    'penalty_rate' => $rate,
                    'penalty_amount' => $penaltyAmount,
                    'applied_date' => $today->toDateString(),
                    'status' => 'recorded',
                ]);

                $installment->status = 'overdue';
                $installment->save();
            });

            $created++;
        }

        return $created;
    }

    private function calculateDaysLate(Carbon $dueDate, Carbon $today, int $graceDays): int
    {
        $elapsed = $dueDate->diffInDays($today, false);

        return max(0, $elapsed - $graceDays);
    }
}


