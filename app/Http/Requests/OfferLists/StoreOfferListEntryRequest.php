<?php

namespace App\Http\Requests\OfferLists;

use App\Models\OfferListEntry;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfferListEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OfferListEntry::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->fieldRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'price' => $this->input('price') === '' ? null : $this->input('price'),
            'factory_price' => $this->input('factory_price') === '' ? null : $this->input('factory_price'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fieldRules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'product_quantity' => ['nullable', 'string'],
            'price' => ['nullable', 'string', 'max:255'],
            'factory_name' => ['nullable', 'string', 'max:255'],
            'factory_price' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'offered_on' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Müşteri adı zorunludur.',
            'offered_on.required' => 'Tarih zorunludur.',
        ];
    }
}
