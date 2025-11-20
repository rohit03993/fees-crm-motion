<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRescheduleRequest;
use App\Models\Installment;
use App\Models\Student;
use App\Services\RescheduleService;
use Illuminate\Http\RedirectResponse;

class RescheduleController extends Controller
{
    public function __construct(private RescheduleService $rescheduleService)
    {
    }

    public function store(StoreRescheduleRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('create', \App\Models\Reschedule::class);
        
        $installment = Installment::findOrFail($request->validated()['installment_id']);

        $this->rescheduleService->requestReschedule($student, $installment, $request->validated());

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'Reschedule request submitted for review.');
    }
}


