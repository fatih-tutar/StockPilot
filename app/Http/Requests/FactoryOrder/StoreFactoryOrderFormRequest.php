<?php

namespace App\Http\Requests\FactoryOrder;

use App\Models\FactoryOrderForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoryOrderFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FactoryOrderForm::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'factory_id' => ['required', 'integer', Rule::exists('factories', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'factory_id.required' => 'Form için bir fabrika seçin.',
        ];
    }
}
