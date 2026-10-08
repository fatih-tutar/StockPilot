<?php

namespace App\Http\Requests\Leave;

use App\Models\Leave;
use App\Models\User;
use App\Support\LeaveRules;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Leave::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $manages = $this->user()?->can('leaves.manage') ?? false;

        return [
            'start_on' => ['required', 'date'],
            'return_on' => ['required', 'date', 'after:start_on'],
            'user_id' => [$manages ? 'required' : 'prohibited', 'integer', 'exists:users,id'],
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
            'user_id.required' => 'Personel seçin.',
            'user_id.prohibited' => 'İzin yalnızca kendi adınıza girilebilir.',
            'user_id.exists' => 'Personel bulunamadı.',
        ];
    }

    public function subject(): User
    {
        if ($this->user()->can('leaves.manage')) {
            return User::query()->findOrFail($this->integer('user_id'));
        }

        return $this->user();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $subject = $this->subject();

            LeaveRules::assertBookable(
                $validator,
                $this->user(),
                $subject,
                (bool) $subject->in_office,
                $subject->company_id,
                $this->date('start_on'),
                $this->date('return_on'),
            );
        });
    }
}
