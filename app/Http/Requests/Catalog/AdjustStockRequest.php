<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity_piece_delta' => ['nullable', 'integer'],
            'quantity_pallet_delta' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $piece = (int) $this->input('quantity_piece_delta', 0);
            $pallet = (int) $this->input('quantity_pallet_delta', 0);

            if ($piece === 0 && $pallet === 0) {
                $validator->errors()->add('quantity_piece_delta', 'Enter at least one non-zero stock change.');
            }
        });
    }
}
