<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Reschedule;
use App\Models\Student;
use App\Models\WhatsappLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleService
{
    public function requestReschedule(Student $student, Installment $installment, array $data): Reschedule
    {
        $this->ensureInstallmentBelongsToStudent($student, $installment);

        $existingAttempts = $installment->reschedules()->count();

        if ($existingAttempts >= 2) {
            throw ValidationException::withMessages([
                'installment_id' => 'Only two reschedule attempts are allowed per installment.',
            ]);
        }

        $newDueDate = Carbon::parse($data['new_due_date'])->startOfDay();
        $oldDueDate = $installment->due_date->copy();

        if ($newDueDate->lte($oldDueDate)) {
            throw ValidationException::withMessages([
                'new_due_date' => 'New due date must be after the current due date.',
            ]);
        }

        return DB::transaction(function () use ($student, $installment, $data, $existingAttempts, $oldDueDate, $newDueDate) {
            $reschedule = Reschedule::create([
                'student_id' => $student->id,
                'installment_id' => $installment->id,
                'old_due_date' => $oldDueDate,
                'new_due_date' => $newDueDate,
                'reason' => $data['reason'],
                'attempt_number' => $existingAttempts + 1,
                'status' => 'pending',
                'requested_by' => Auth::id(),
            ]);

            $this->logWhatsappRequest($student, $installment, $reschedule);

            return $reschedule;
        });
    }

    public function approveReschedule(Reschedule $reschedule, ?string $notes = null): Reschedule
    {
        if ($reschedule->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending reschedules can be approved.',
            ]);
        }

        return DB::transaction(function () use ($reschedule, $notes) {
            $reschedule->status = 'approved';
            $reschedule->decision_notes = $notes;
            $reschedule->approved_by = Auth::id();
            $reschedule->approved_at = now();
            $reschedule->save();

            $installment = $reschedule->installment;
            $installment->due_date = $reschedule->new_due_date;
            $installment->status = 'rescheduled';
            $installment->save();

            $this->logWhatsappDecision($reschedule, 'approved');

            return $reschedule->load(['installment', 'student']);
        });
    }

    public function rejectReschedule(Reschedule $reschedule, ?string $notes = null): Reschedule
    {
        if ($reschedule->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending reschedules can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($reschedule, $notes) {
            $reschedule->status = 'rejected';
            $reschedule->decision_notes = $notes;
            $reschedule->approved_by = Auth::id();
            $reschedule->approved_at = now();
            $reschedule->save();

            $this->logWhatsappDecision($reschedule, 'rejected');

            return $reschedule->load(['installment', 'student']);
        });
    }

    private function ensureInstallmentBelongsToStudent(Student $student, Installment $installment): void
    {
        $installment->loadMissing('fee');

        if ($installment->fee->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'installment_id' => 'Selected installment does not belong to this student.',
            ]);
        }
    }

    private function logWhatsappRequest(Student $student, Installment $installment, Reschedule $reschedule): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = sprintf(
            'Reschedule request submitted for installment #%s (₹%s). Proposed new due date: %s.',
            $installment->installment_number,
            number_format($installment->amount, 2),
            $reschedule->new_due_date->format('d M Y')
        );

        WhatsappLog::create([
            'student_id' => $student->id,
            'installment_id' => $installment->id,
            'message_type' => 'reschedule_request',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
        ]);
    }

    private function logWhatsappDecision(Reschedule $reschedule, string $decision): void
    {
        $student = $reschedule->student;
        $installment = $reschedule->installment;
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = match ($decision) {
            'approved' => sprintf(
                'Your reschedule request for installment #%s is approved. New due date: %s.',
                $installment->installment_number,
                $reschedule->new_due_date->format('d M Y')
            ),
            default => sprintf(
                'Your reschedule request for installment #%s was not approved. We will contact you for next steps.',
                $installment->installment_number
            ),
        };

        WhatsappLog::create([
            'student_id' => $student->id,
            'installment_id' => $installment->id,
            'message_type' => 'reschedule_'.$decision,
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
        ]);
    }
}


