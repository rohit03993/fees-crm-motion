<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDiscountRequest;
use App\Models\Student;
use App\Services\DiscountService;
use Illuminate\Http\RedirectResponse;

class DiscountController extends Controller
{
    public function __construct(private DiscountService $discountService)
    {
    }

    public function store(StoreDiscountRequest $request, Student $student): RedirectResponse
    {
        $this->authorize('create', \App\Models\Discount::class);
        
        try {
            $data = $request->validated();
            $data['document'] = $request->file('document');

            $this->discountService->requestDiscount($student, $data);

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Discount request submitted for review.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('students.show', $student)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->route('students.show', $student)
                ->with('error', 'Failed to submit discount request: ' . $e->getMessage())
                ->withInput();
        }
    }
}


