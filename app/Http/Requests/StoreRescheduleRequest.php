<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'installment_id' => ['required', 'integer', 'exists:installments,id'],
            'new_due_date' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}


