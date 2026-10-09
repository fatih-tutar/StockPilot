<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->whereNull('deleted_at')->whereNotNull('parent_id'),
            ],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
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
            'warehouse_low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'mold_number' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Alt kategori seçin.',
            'category_id.exists' => 'Ürün yalnızca bir alt kategoriye eklenebilir.',
        ];
    }
}
