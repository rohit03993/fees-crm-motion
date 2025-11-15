<?php

namespace App\Console\Commands;

use App\Models\MiscCharge;
use App\Models\Setting;
use Illuminate\Console\Command;

class FixGstPenaltyCalculations extends Command
{
    protected $signature = 'gst-penalty:fix {--student-id= : Fix for specific student ID only} {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Fix GST penalty calculations for existing records (recalculate only GST amount, not excess + GST)';

    public function handle(): int
    {
        $studentId = $this->option('student-id');
        $dryRun = $this->option('dry-run');

        $gstRate = (float) Setting::getValue('penalty.gst_percentage', config('fees.gst_percentage', 18.0));

        $this->info("GST Rate: {$gstRate}%");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (will update records)'));
        $this->newLine();

        $query = MiscCharge::where('label', 'like', 'GST Penalty%');

        if ($studentId) {
            $query->where('student_id', $studentId);
        }

        $gstPenalties = $query->get();

        if ($gstPenalties->isEmpty()) {
            $this->info('No GST penalty records found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$gstPenalties->count()} GST penalty record(s).");
        $this->newLine();

        $fixed = 0;
        $skipped = 0;

        foreach ($gstPenalties as $penalty) {
            // Extract excess amount from label
            // Old format: "GST Penalty (Online overage ₹10000.00)"
            // New format: "GST Penalty on Online Overage (Excess ₹10000.00 + 18% GST = ₹1800.00)"
            preg_match('/₹([\d,]+\.?\d*)/', $penalty->label, $matches);
            
            if (empty($matches[1])) {
                $this->warn("Skipping record ID {$penalty->id}: Could not extract excess amount from label: {$penalty->label}");
                $skipped++;
                continue;
            }

            $excessAmount = (float) str_replace(',', '', $matches[1]);

            // Calculate correct GST penalty (only GST on excess)
            $correctGstPenalty = round($excessAmount * ($gstRate / 100), 2);

            // Check if record needs fixing
            if (abs($penalty->amount - $correctGstPenalty) < 0.01) {
                $this->info("✓ Record ID {$penalty->id} (Student #{$penalty->student_id}): Already correct (₹{$penalty->amount})");
                $skipped++;
                continue;
            }

            $oldAmount = $penalty->amount;
            $difference = $oldAmount - $correctGstPenalty;

            $this->line("Record ID {$penalty->id} (Student #{$penalty->student_id}):");
            $this->line("  Excess Amount: ₹{$excessAmount}");
            $this->line("  Old Penalty: ₹{$oldAmount}");
            $this->line("  Correct Penalty: ₹{$correctGstPenalty}");
            $this->line("  Difference: ₹{$difference}");
            
            if (!$dryRun) {
                // Update the penalty amount
                $penalty->amount = $correctGstPenalty;
                
                // Update the label to new format
                $penalty->label = 'GST Penalty on Online Overage (Excess ₹' . number_format($excessAmount, 2) . ' + ' . number_format($gstRate, 2) . '% GST = ₹' . number_format($correctGstPenalty, 2) . ')';
                
                $penalty->save();
                
                $this->info("  ✓ Updated!");
            } else {
                $this->comment("  [DRY RUN] Would update to ₹{$correctGstPenalty}");
            }
            
            $fixed++;
            $this->newLine();
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Fixed: {$fixed}");
        $this->info("  Skipped: {$skipped}");
        $this->info("  Total: {$gstPenalties->count()}");

        if ($dryRun) {
            $this->newLine();
            $this->comment("This was a DRY RUN. No changes were made.");
            $this->comment("Run without --dry-run to apply changes.");
        }

        return Command::SUCCESS;
    }
}

