<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandleRescheduleDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected'],
            'decision_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}


