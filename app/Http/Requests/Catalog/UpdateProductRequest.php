<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('mold_number')) {
            $this->merge([
                'mold_number' => $this->input('mold_number') === '' ? null : $this->input('mold_number'),
            ]);
        }
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'warehouse_low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'quantity_piece' => ['nullable', 'integer', 'min:0'],
            'quantity_pallet' => ['nullable', 'integer', 'min:0'],
            'warehouse_quantity' => ['nullable', 'integer', 'min:0'],
            'shelf' => ['nullable', 'string', 'max:255'],
            'unit_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'length_measure' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'factory_id' => ['nullable', 'integer', Rule::exists('factories', 'id')],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
            'default_order_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'mold_number' => ['nullable', 'string', 'max:32'],
        ];
    }
}
