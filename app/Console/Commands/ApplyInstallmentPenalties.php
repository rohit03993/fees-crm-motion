<?php

namespace App\Console\Commands;

use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyInstallmentPenalties extends Command
{
    protected $signature = 'penalties:apply {--date= : Run the job as if today is the given Y-m-d date}';

    protected $description = 'Apply overdue penalties to installments beyond the grace period.';

    public function __construct(private PenaltyService $penaltyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $runDate = $dateOption ? Carbon::parse($dateOption) : null;

        $count = $this->penaltyService->applyPenalties($runDate);

        $this->info("Applied {$count} penalties.");

        return Command::SUCCESS;
    }
}


