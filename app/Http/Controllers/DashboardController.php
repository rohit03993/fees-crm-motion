<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function index(): View
    {
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');

        $taxData = $this->dashboardService->getTaxAndSafeRatioData($startDate, $endDate);
        $quickStats = $this->dashboardService->getQuickStats();

        return view('dashboard', [
            'taxData' => $taxData,
            'quickStats' => $quickStats,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}

