<?php

namespace App\Http\Requests;

use App\Enums\StaffDocument;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = [];

        foreach (['phone', 'phone_2', 'address', 'title', 'hired_on'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $fields[$field] = null;
            }
        }

        if ($fields !== []) {
            $this->merge($fields);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'hired_on' => ['nullable', 'date'],
            StaffDocument::Photo->value => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            StaffDocument::IdentityCard->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            StaffDocument::ApplicationForm->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            StaffDocument::ResidenceCertificate->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            StaffDocument::HealthReport->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ad zorunludur.',
            'email.required' => 'E-posta zorunludur.',
            'email.email' => 'Geçerli bir e-posta adresi girin.',
        ];
    }
}
