<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentBasicInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'guardian_1_name' => ['required', 'string', 'max:255'],
            'guardian_1_whatsapp' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Student name is required.',
            'guardian_1_name.required' => 'Father\'s name is required.',
            'guardian_1_whatsapp.required' => 'Mobile number is required.',
            'guardian_1_whatsapp.regex' => 'Mobile number must be exactly 10 digits.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean and format guardian WhatsApp number (remove +91 and non-digits)
        $whatsapp = $this->input('guardian_1_whatsapp');
        if ($whatsapp) {
            $whatsapp = preg_replace('/^\+91/', '', $whatsapp);
            $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
            $whatsapp = substr($whatsapp, 0, 10);
            
            $this->merge([
                'guardian_1_whatsapp' => $whatsapp,
            ]);
        }
    }
}

