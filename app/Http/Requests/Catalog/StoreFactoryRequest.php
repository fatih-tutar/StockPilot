<?php

namespace App\Http\Requests\Catalog;

use App\Models\Factory;
use Illuminate\Foundation\Http\FormRequest;

class StoreFactoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Factory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'fine_labor_cost' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
