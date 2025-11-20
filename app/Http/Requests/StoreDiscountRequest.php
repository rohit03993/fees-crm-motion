<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'installment_id' => ['required', 'exists:installments,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'document' => ['nullable', 'file', 'max:2048', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }

    public function messages(): array
    {
        return [
            'installment_id.required' => 'Please select an installment.',
            'installment_id.exists' => 'The selected installment is invalid.',
            'amount.required' => 'Discount amount is required.',
            'amount.numeric' => 'Discount amount must be a valid number.',
            'amount.min' => 'Discount amount must be at least ₹0.01.',
            'reason.required' => 'Reason is required.',
            'reason.min' => 'Reason must be at least 10 characters.',
            'reason.max' => 'Reason must not exceed 500 characters.',
            'document.max' => 'Document size must not exceed 2MB.',
            'document.mimes' => 'Document must be a PDF, JPG, JPEG, or PNG file.',
        ];
    }
}


