<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePenaltySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && optional(auth()->user())->isAdmin();
    }

    public function rules(): array
    {
        return [
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'reminder_cadence' => ['required', 'integer', 'min:1', 'max:30'],
            'gst_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}


