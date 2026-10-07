<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[\p{L}\p{N} _-]+$/u',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Rol adı zorunludur.',
            'name.unique' => 'Bu rol adı zaten var.',
            'name.regex' => 'Rol adında yalnızca harf, rakam, boşluk, tire ve alt çizgi kullanılabilir.',
        ];
    }
}
