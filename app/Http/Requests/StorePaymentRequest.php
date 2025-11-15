<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMode = $this->input('payment_mode');

        $rules = [
            'payment_type' => ['required', Rule::in(['tuition', 'miscellaneous', 'penalty'])],
            'amount_received' => ['required', 'numeric', 'min:0'], // Allow 0 when credit fully covers payment
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'bank_transfer', 'cheque'])],
            'payment_date' => ['required', 'date'],
            'voucher_number' => ['required', 'string', 'max:191'], // Required for all modes
            'employee_name' => ['required', 'string', 'max:191'], // Required for all modes
            'remarks' => ['nullable', 'string'],
            'installment_id' => ['nullable', 'required_if:payment_type,tuition', 'integer', 'exists:installments,id'],
            'misc_charge_id' => ['nullable', 'required_if:payment_type,miscellaneous', 'integer', 'exists:misc_charges,id'],
            'penalty_type' => ['nullable', 'required_if:payment_type,penalty', Rule::in(['late_fee', 'gst'])],
            'penalty_id' => ['nullable', 'required_if:penalty_type,late_fee', 'integer', 'exists:penalties,id'],
            'gst_penalty_charge_id' => ['nullable', 'required_if:penalty_type,gst', 'integer', 'exists:misc_charges,id'],
            'create_remaining_installment' => ['nullable', 'boolean'],
            'remaining_installment_due_date' => ['nullable', 'required_if:create_remaining_installment,1', 'date', 'after_or_equal:today'],
            'use_credit_balance' => ['nullable', 'boolean'], // NEW: Option to use credit balance
        ];

        // Online modes (UPI, Bank Transfer): UTR number (transaction_id) and Bank (bank_id)
        if (in_array($paymentMode, ['upi', 'bank_transfer'])) {
            $rules['transaction_id'] = ['required', 'string', 'max:191']; // UTR number
            $rules['bank_id'] = ['required', 'integer', 'exists:banks,id']; // Bank selection
        }

        // Cheque mode: Cheque number (transaction_id), Bank (bank_id), and Deposited to bank (deposited_to)
        if ($paymentMode === 'cheque') {
            $rules['transaction_id'] = ['required', 'string', 'max:191']; // Cheque number
            $rules['bank_id'] = ['required', 'integer', 'exists:banks,id']; // Bank of cheque
            $rules['deposited_to'] = ['required', 'string', 'max:191']; // Bank where cheque was deposited
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'create_remaining_installment' => filter_var($this->input('create_remaining_installment', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}


