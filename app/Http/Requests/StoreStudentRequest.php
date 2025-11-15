<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'student_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:1024'], // Max 1 MB
            'guardian_1_name' => ['required', 'string', 'max:255'],
            'guardian_1_whatsapp' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'guardian_1_relation' => ['required', 'string', 'max:255'],
            'guardian_1_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:1024'], // Max 1 MB - passport size
            'guardian_2_name' => ['nullable', 'string', 'max:255'],
            'guardian_2_whatsapp' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'guardian_2_relation' => ['nullable', 'string', 'max:255'],
            'guardian_2_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:1024'], // Max 1 MB - passport size
            'course_id' => ['required', 'exists:courses,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'admission_date' => ['required', 'date'],
            'program_start_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'total_fee' => ['required', 'numeric', 'min:0'],
            'cash_allowance' => ['required', 'numeric', 'min:0'],
            'online_allowance' => ['required', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'in:full,installments'],
            'installment_meta.count' => ['nullable', 'integer', 'min:1'],
            'installment_meta.frequency' => ['nullable', 'integer', 'min:1'],
            'installment_meta.notes' => ['nullable', 'string'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.due_date' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'min:0'],
            'installments.*.grace_end_date' => ['nullable', 'date'],
            'misc_charges' => ['nullable', 'array'],
            'misc_charges.*.label' => ['required_with:misc_charges.*', 'string', 'max:255'],
            'misc_charges.*.amount' => ['required_with:misc_charges.*', 'numeric', 'min:0'],
            'misc_charges.*.due_date' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $installments = collect($this->input('installments', []))
            ->filter(function ($row) {
                $amount = $row['amount'] ?? null;
                $dueDate = $row['due_date'] ?? null;

                return $dueDate && $amount !== null && $amount !== '';
            })
            ->values()
            ->all();

        // Filter out empty misc charges rows
        $miscCharges = collect($this->input('misc_charges', []))
            ->filter(function ($row) {
                $label = trim($row['label'] ?? '');
                $amount = $row['amount'] ?? null;

                return !empty($label) && $amount !== null && $amount !== '' && $amount > 0;
            })
            ->values()
            ->all();

        $meta = $this->input('installment_meta', []);

        // Clean and format guardian WhatsApp numbers (prepend +91)
        $cleanPhoneNumber = function($number) {
            if (empty($number)) return null;
            $number = preg_replace('/^\+91/', '', $number);
            $number = preg_replace('/[^0-9]/', '', $number);
            $number = substr($number, 0, 10);
            return !empty($number) ? '+91' . $number : null;
        };

        $guardian1Whatsapp = $cleanPhoneNumber($this->input('guardian_1_whatsapp'));
        $guardian2Whatsapp = $this->input('guardian_2_whatsapp') ? $cleanPhoneNumber($this->input('guardian_2_whatsapp')) : null;

        // Store original values (without +91) for validation
        $guardian1WhatsappForValidation = preg_replace('/^\+91|[^0-9]/', '', $this->input('guardian_1_whatsapp', ''));
        $guardian2WhatsappForValidation = $this->input('guardian_2_whatsapp') ? preg_replace('/^\+91|[^0-9]/', '', $this->input('guardian_2_whatsapp')) : null;

        $this->merge([
            'total_fee' => (float) $this->input('total_fee', 0),
            'cash_allowance' => (float) $this->input('cash_allowance', 0),
            'online_allowance' => (float) $this->input('online_allowance', 0),
            'guardian_1_whatsapp' => $guardian1WhatsappForValidation, // Store without +91 for validation
            'guardian_1_whatsapp_formatted' => $guardian1Whatsapp, // Store with +91 for database
            'guardian_2_whatsapp' => $guardian2WhatsappForValidation, // Store without +91 for validation
            'guardian_2_whatsapp_formatted' => $guardian2Whatsapp, // Store with +91 for database
            'installments' => $installments,
            'misc_charges' => $miscCharges,
            'installment_meta' => [
                'count' => Arr::get($meta, 'count'),
                'frequency' => Arr::get($meta, 'frequency', 1), // Default to 1 month if not provided
                'first_due_date' => Arr::get($meta, 'first_due_date'),
                'notes' => Arr::get($meta, 'notes'),
            ],
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $totalFee = round((float) $this->input('total_fee', 0), 2);
            $cashAllowance = round((float) $this->input('cash_allowance', 0), 2);
            $onlineAllowance = round((float) $this->input('online_allowance', 0), 2);

            if (abs(($cashAllowance + $onlineAllowance) - $totalFee) > 0.01) {
                $validator->errors()->add('cash_allowance', 'Cash and online allowances must add up to the total programme fee.');
            }

            $installments = collect($this->input('installments', []));
            $sum = round($installments->sum(fn ($item) => (float) $item['amount']), 2);

            if (abs($sum - $totalFee) > 0.01) {
                $validator->errors()->add('installments', 'Installment amounts must match the total programme fee.');
            }

            if ($this->input('payment_mode') === 'full' && $installments->count() !== 1) {
                $validator->errors()->add('installments', 'Full payment mode should have exactly one installment.');
            }
        });
    }
}
