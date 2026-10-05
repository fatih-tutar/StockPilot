<?php

namespace App\Http\Requests\FactoryOrder;

use App\Models\FactoryOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FactoryOrder::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'length' => $this->input('length') === '' ? null : $this->input('length'),
            'contact_name' => $this->input('contact_name') === '' ? null : $this->input('contact_name'),
            'due_on' => $this->input('due_on') === '' ? null : $this->input('due_on'),
            'prepared_by_user_id' => $this->input('prepared_by_user_id') === '' ? null : $this->input('prepared_by_user_id'),
            'pallet_count' => $this->input('pallet_count') === '' || $this->input('pallet_count') === null
                ? 0
                : $this->input('pallet_count'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'factory_id' => ['required', 'integer', Rule::exists('factories', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'length' => ['nullable', 'string', 'max:64'],
            'pallet_count' => ['nullable', 'integer', 'min:0'],
            'due_on' => ['nullable', 'date'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'prepared_by_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'factory_id.required' => 'Fabrika seçin.',
            'product_id.required' => 'Ürün seçin.',
            'quantity.required' => 'Adet zorunludur.',
            'quantity.min' => 'Adet en az 1 olmalıdır.',
        ];
    }
}
