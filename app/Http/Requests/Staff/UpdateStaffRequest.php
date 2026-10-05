<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffDocument;
use App\Enums\UserAccessLevel;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof User && ($this->user()?->can('update', $staff) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->input('email') === '' ? null : $this->input('email'),
            'phone' => $this->input('phone') === '' ? null : $this->input('phone'),
            'phone_2' => $this->input('phone_2') === '' ? null : $this->input('phone_2'),
            'address' => $this->input('address') === '' ? null : $this->input('address'),
            'title' => $this->input('title') === '' ? null : $this->input('title'),
            'hired_on' => $this->input('hired_on') === '' ? null : $this->input('hired_on'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff instanceof User ? $staff->id : null)],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_2' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
            'hired_on' => ['nullable', 'date'],
            'access_level' => ['required', Rule::enum(UserAccessLevel::class)],
            'access_flags' => ['nullable', 'array'],
            'access_flags.*' => ['boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
            'access_level.required' => 'Yetki düzeyi zorunludur.',
            'password.min' => 'Şifre en az 8 karakter olmalıdır.',
            'password.confirmed' => 'Şifre tekrarı eşleşmiyor.',
        ];
    }
}
