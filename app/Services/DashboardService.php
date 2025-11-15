<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const OFFLINE_PAYMENT_MODES = ['cash'];
    private const DEFAULT_SAFE_RATIO_THRESHOLD = 0.8; // 80%

    public function getTaxAndSafeRatioData(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Payment::query()
            ->select([
                'payment_mode',
                DB::raw('SUM(base_amount) as total_base'),
                DB::raw('SUM(gst_amount) as total_gst'),
                DB::raw('SUM(amount_received) as total_received'),
                DB::raw('COUNT(*) as payment_count'),
            ])
            ->groupBy('payment_mode');

        if ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('payment_date', '<=', $endDate);
        }

        $paymentStats = $query->get()->keyBy('payment_mode');

        // Cash payments (offline)
        $cashPayments = $paymentStats->filter(function ($stat) {
            return in_array($stat->payment_mode, self::OFFLINE_PAYMENT_MODES, true);
        });

        $cashBase = $cashPayments->sum('total_base') ?? 0;
        $cashGst = $cashPayments->sum('total_gst') ?? 0;
        $cashTotal = $cashPayments->sum('total_received') ?? 0;
        $cashCount = $cashPayments->sum('payment_count') ?? 0;

        // Online payments (upi, bank_transfer, cheque)
        $onlinePayments = $paymentStats->filter(function ($stat) {
            return !in_array($stat->payment_mode, self::OFFLINE_PAYMENT_MODES, true);
        });

        $onlineBase = $onlinePayments->sum('total_base') ?? 0;
        $onlineGst = $onlinePayments->sum('total_gst') ?? 0;
        $onlineTotal = $onlinePayments->sum('total_received') ?? 0;
        $onlineCount = $onlinePayments->sum('payment_count') ?? 0;

        // Overall totals
        $totalBase = $cashBase + $onlineBase;
        $totalGst = $cashGst + $onlineGst;
        $totalReceived = $cashTotal + $onlineTotal;
        $totalCount = $cashCount + $onlineCount;

        // Safe ratio calculation: (online base) / (cash base)
        // If cash base is 0, ratio is undefined
        $safeRatio = null;
        $safeRatioPercentage = null;
        $isSafeRatioExceeded = false;

        if ($cashBase > 0) {
            $safeRatio = round($onlineBase / $cashBase, 4);
            $safeRatioPercentage = round($safeRatio * 100, 2);
            $threshold = $this->getSafeRatioThreshold();
            $isSafeRatioExceeded = $safeRatio > $threshold;
        } elseif ($onlineBase > 0) {
            // If cash base is 0 but online base exists, ratio is infinite (exceeded)
            $safeRatio = null;
            $safeRatioPercentage = null;
            $isSafeRatioExceeded = true;
        }

        // Payment breakdown by mode
        $paymentBreakdown = $paymentStats->map(function ($stat) {
            return [
                'mode' => $stat->payment_mode,
                'base' => (float) $stat->total_base,
                'gst' => (float) $stat->total_gst,
                'total' => (float) $stat->total_received,
                'count' => (int) $stat->payment_count,
                'is_online' => !in_array($stat->payment_mode, self::OFFLINE_PAYMENT_MODES, true),
            ];
        })->values();

        // Recent payments for chart data
        $recentPayments = Payment::query()
            ->when($startDate, fn($q) => $q->where('payment_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('payment_date', '<=', $endDate))
            ->select([
                'payment_date',
                'payment_mode',
                DB::raw('SUM(base_amount) as daily_base'),
                DB::raw('SUM(gst_amount) as daily_gst'),
                DB::raw('SUM(amount_received) as daily_total'),
            ])
            ->groupBy('payment_date', 'payment_mode')
            ->orderBy('payment_date', 'desc')
            ->limit(30)
            ->get();

        $dailyData = $recentPayments->groupBy('payment_date')->map(function ($dayPayments, $dateKey) {
            $cashDaily = $dayPayments->filter(fn($p) => in_array($p->payment_mode, self::OFFLINE_PAYMENT_MODES, true));
            $onlineDaily = $dayPayments->filter(fn($p) => !in_array($p->payment_mode, self::OFFLINE_PAYMENT_MODES, true));

            return [
                'date' => $dateKey, // Use the groupBy key (string format Y-m-d)
                'cash_total' => (float) $cashDaily->sum('daily_total'),
                'online_total' => (float) $onlineDaily->sum('daily_total'),
                'cash_base' => (float) $cashDaily->sum('daily_base'),
                'online_base' => (float) $onlineDaily->sum('daily_base'),
                'total' => (float) $dayPayments->sum('daily_total'),
            ];
        })->values()->reverse();

        return [
            'cash' => [
                'base' => round($cashBase, 2),
                'gst' => round($cashGst, 2),
                'total' => round($cashTotal, 2),
                'count' => $cashCount,
            ],
            'online' => [
                'base' => round($onlineBase, 2),
                'gst' => round($onlineGst, 2),
                'total' => round($onlineTotal, 2),
                'count' => $onlineCount,
            ],
            'totals' => [
                'base' => round($totalBase, 2),
                'gst' => round($totalGst, 2),
                'total' => round($totalReceived, 2),
                'count' => $totalCount,
            ],
            'safe_ratio' => [
                'ratio' => $safeRatio,
                'percentage' => $safeRatioPercentage,
                'threshold' => $this->getSafeRatioThreshold(),
                'threshold_percentage' => round($this->getSafeRatioThreshold() * 100, 2),
                'is_exceeded' => $isSafeRatioExceeded,
                'cash_base' => round($cashBase, 2),
                'online_base' => round($onlineBase, 2),
            ],
            'payment_breakdown' => $paymentBreakdown,
            'daily_data' => $dailyData,
        ];
    }

    private function getSafeRatioThreshold(): float
    {
        // TODO: Get from settings table in Module 10
        return (float) config('fees.safe_ratio_threshold', self::DEFAULT_SAFE_RATIO_THRESHOLD);
    }

    public function getQuickStats(): array
    {
        $totalStudents = \App\Models\Student::count();
        $totalPayments = Payment::count();
        $totalCollection = Payment::sum('amount_received') ?? 0;
        $pendingReschedules = \App\Models\Reschedule::where('status', 'pending')->count();
        $pendingDiscounts = \App\Models\Discount::where('status', 'pending')->count();

        return [
            'total_students' => $totalStudents,
            'total_payments' => $totalPayments,
            'total_collection' => round($totalCollection, 2),
            'pending_reschedules' => $pendingReschedules,
            'pending_discounts' => $pendingDiscounts,
        ];
    }
}

