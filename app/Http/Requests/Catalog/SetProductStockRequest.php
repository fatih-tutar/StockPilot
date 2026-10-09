<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SetProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', $this->route('product')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'quantity_piece' => ['nullable', 'integer', 'min:0'],
            'warehouse_quantity' => ['nullable', 'integer', 'min:0'],
            'quantity_pallet' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_piece.integer' => 'Adet tam sayı olmalı.',
            'quantity_piece.min' => 'Adet 0 veya daha büyük olmalı.',
            'warehouse_quantity.integer' => 'Depo adet tam sayı olmalı.',
            'warehouse_quantity.min' => 'Depo adet 0 veya daha büyük olmalı.',
            'quantity_pallet.integer' => 'Palet tam sayı olmalı.',
            'quantity_pallet.min' => 'Palet 0 veya daha büyük olmalı.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $filled = collect(['quantity_piece', 'warehouse_quantity', 'quantity_pallet'])
                ->contains(fn (string $key) => $this->filled($key));

            if (! $filled) {
                $validator->errors()->add('quantity_piece', 'En az bir yeni adet girin.');
            }
        });
    }
}
