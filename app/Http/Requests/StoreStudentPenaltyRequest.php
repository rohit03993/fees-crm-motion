<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentPenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('student'));
    }

    public function rules(): array
    {
        return [
            'installment_id' => ['nullable', 'exists:installments,id'],
            'penalty_amount' => ['required', 'numeric', 'min:0.01'],
            'penalty_type_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'installment_id.exists' => 'Selected installment is invalid.',
            'penalty_amount.required' => 'Penalty amount is required.',
            'penalty_amount.numeric' => 'Penalty amount must be a valid number.',
            'penalty_amount.min' => 'Penalty amount must be at least 0.01.',
            'penalty_type_name.required' => 'Type of penalty is required.',
            'penalty_type_name.string' => 'Type of penalty must be text.',
            'penalty_type_name.max' => 'Type of penalty must not exceed 100 characters.',
        ];
    }
}

