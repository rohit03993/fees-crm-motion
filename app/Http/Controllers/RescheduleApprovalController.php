<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleRescheduleDecisionRequest;
use App\Models\Reschedule;
use App\Services\RescheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RescheduleApprovalController extends Controller
{
    public function __construct(private RescheduleService $rescheduleService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Reschedule::class);

        $reschedules = Reschedule::with(['student', 'installment', 'requester', 'approver'])
            ->latest()
            ->paginate(10);

        return view('reschedules.index', compact('reschedules'));
    }

    public function update(HandleRescheduleDecisionRequest $request, Reschedule $reschedule): RedirectResponse
    {
        $this->authorize('update', $reschedule);

        $data = $request->validated();

        if ($data['decision'] === 'approved') {
            $this->rescheduleService->approveReschedule($reschedule, $data['decision_notes'] ?? null);
            $message = 'Reschedule approved and installment updated.';
        } else {
            $this->rescheduleService->rejectReschedule($reschedule, $data['decision_notes'] ?? null);
            $message = 'Reschedule rejected.';
        }

        return redirect()->route('reschedules.index')->with('success', $message);
    }
}


