<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandleDiscountDecisionRequest;
use App\Models\Discount;
use App\Services\DiscountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DiscountApprovalController extends Controller
{
    public function __construct(private DiscountService $discountService)
    {
    }

    public function index(): View
    {
        $this->authorize('viewAny', Discount::class);

        $discounts = Discount::with(['student', 'installment', 'requester', 'approver'])
            ->latest()
            ->paginate(10);

        return view('discounts.index', compact('discounts'));
    }

    public function update(HandleDiscountDecisionRequest $request, Discount $discount): RedirectResponse
    {
        $this->authorize('update', $discount);

        $data = $request->validated();

        if ($data['decision'] === 'approved') {
            $this->discountService->approveDiscount($discount, $data['decision_notes'] ?? null);
            $message = 'Discount approved.';
        } else {
            $this->discountService->rejectDiscount($discount, $data['decision_notes'] ?? null);
            $message = 'Discount rejected.';
        }

        return redirect()->route('discounts.index')->with('success', $message);
    }
}


