<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ScheduleInstallmentReminders extends Command
{
    protected $signature = 'reminders:schedule {--date= : Run the job as if today is the given Y-m-d date}';

    protected $description = 'Queue WhatsApp reminders for overdue installments.';

    public function __construct(private ReminderService $reminderService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $runDate = $dateOption ? Carbon::parse($dateOption) : null;

        $count = $this->reminderService->scheduleOverdueReminders($runDate);

        $this->info("Scheduled {$count} reminders.");

        return Command::SUCCESS;
    }
}


