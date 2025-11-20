<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePenaltySettingsRequest;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PenaltySettingsController extends Controller
{
    public function edit(): View
    {
        $this->authorize('view-settings');

        $settings = [
            'grace_days' => Setting::getValue('penalty.grace_days', config('fees.penalty.grace_days')),
            'rate_percent' => Setting::getValue('penalty.rate_percent_per_day', config('fees.penalty.rate_percent_per_day')),
            'reminder_cadence' => Setting::getValue('reminder.cadence_days', config('fees.penalty.reminder_frequency_days')),
            'gst_percentage' => Setting::getValue('penalty.gst_percentage', config('fees.gst_percentage', 18.0)),
        ];

        $studentCount = Student::count();

        return view('settings.penalties', compact('settings', 'studentCount'));
    }

    public function update(UpdatePenaltySettingsRequest $request): RedirectResponse
    {
        $this->authorize('update-settings');

        $data = $request->validated();

        Setting::setValue('penalty.grace_days', $data['grace_days'], 'number', 'Grace period in days before penalty applies');
        Setting::setValue('penalty.rate_percent_per_day', $data['rate_percent'], 'number', 'Penalty percentage applied per overdue day');
        Setting::setValue('reminder.cadence_days', $data['reminder_cadence'], 'number', 'Reminder cadence in days for overdue installments');
        Setting::setValue('penalty.gst_percentage', $data['gst_percentage'], 'number', 'GST percentage applied on online allowance overage');

        return redirect()
            ->route('settings.penalties.edit')
            ->with('success', 'Penalty and reminder settings updated.');
    }

    public function clearAllStudents(): RedirectResponse
    {
        $this->authorize('clear-students');

        DB::transaction(function () {
            // Delete all students - cascading foreign keys will automatically delete:
            // - Student fees (student_fees)
            // - Installments (via student_fees cascade)
            // - Payments (cascade on student_id)
            // - Penalties (cascade on student_id)
            // - Reminders (cascade on student_id)
            // - Reschedules (cascade on student_id)
            // - Discounts (cascade on student_id)
            // - Misc charges (cascade on student_id)
            // - WhatsApp logs (cascade on student_id)
            Student::query()->delete();
        });

        return redirect()
            ->route('settings.penalties.edit')
            ->with('success', 'All student data has been cleared successfully.');
    }
}


