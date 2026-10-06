<?php

namespace App\Http\Requests\Leave;

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leave = $this->route('leave');

        return $leave instanceof Leave && ($this->user()?->can('update', $leave) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_on' => ['required', 'date'],
            'return_on' => ['required', 'date', 'after:start_on'],
            'status' => ['required', 'integer', Rule::in([
                LeaveStatus::Pending->value,
                LeaveStatus::Approved->value,
                LeaveStatus::Rejected->value,
            ])],
            'user_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_on.required' => 'Başlangıç tarihi zorunludur.',
            'return_on.required' => 'İşe dönüş tarihi zorunludur.',
            'return_on.after' => 'İşe dönüş tarihi başlangıçtan sonra olmalıdır.',
            'status.required' => 'Durum seçin.',
            'status.in' => 'Durum geçersiz.',
            'user_id.prohibited' => 'Personel değiştirilemez.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $leave = $this->route('leave');
            if (! $leave instanceof Leave) {
                return;
            }

            $subject = User::withTrashed()->find($leave->user_id);
            if ($subject === null) {
                $validator->errors()->add('start_on', 'Personel kaydı bulunamadı.');

                return;
            }

            LeaveRules::assertBookable(
                $validator,
                $this->user(),
                $subject,
                $leave->in_office,
                $leave->company_id,
                $this->date('start_on'),
                $this->date('return_on'),
                $leave->id,
            );
        });
    }
}
