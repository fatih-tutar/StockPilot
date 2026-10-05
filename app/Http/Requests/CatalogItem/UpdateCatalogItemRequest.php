<?php

namespace App\Http\Requests\CatalogItem;

use App\Enums\CatalogImage;
use App\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('catalog_item');

        return $item instanceof CatalogItem && ($this->user()?->can('update', $item) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') === '' ? null : $this->input('description'),
            'lines' => $this->normalizedLines(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('catalog_item');
        $currentCode = $item instanceof CatalogItem ? $item->product_code : null;

        return [
            'product_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('catalog_items', 'product_code')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('product_code', '!=', $currentCode)),
            ],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.code' => ['nullable', 'string', 'max:32'],
            'lines.*.model' => ['required', 'string', 'max:128'],
            'lines.*.quantity' => ['nullable', 'string', 'max:64'],
            'lines.*.price' => ['nullable', 'string', 'max:64'],
            CatalogImage::Primary->value => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            CatalogImage::Secondary->value => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_code.required' => 'Ürün no zorunludur.',
            'product_code.unique' => 'Bu ürün no zaten listede.',
            'lines.required' => 'En az bir satır girin.',
            'lines.min' => 'En az bir satır girin.',
            'lines.*.model.required' => 'Model zorunludur.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('lines')) {
                    return;
                }

                $item = $this->route('catalog_item');
                if (! $item instanceof CatalogItem) {
                    return;
                }

                $owned = CatalogItem::query()
                    ->where('product_code', $item->product_code)
                    ->pluck('id');
                $posted = collect($this->input('lines', []))
                    ->pluck('id')
                    ->filter(fn (mixed $id): bool => $id !== null && $id !== '');

                if ($posted->map(fn (mixed $id): int => (int) $id)->diff($owned)->isNotEmpty()) {
                    $validator->errors()->add('lines', 'Satır bu ürüne ait değil.');
                }
            },
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedLines(): array
    {
        $lines = $this->input('lines', []);
        if (! is_array($lines)) {
            return [];
        }

        return array_map(function (mixed $line): array {
            if (! is_array($line)) {
                return [];
            }

            foreach (['id', 'code', 'model', 'quantity', 'price'] as $key) {
                if (($line[$key] ?? null) === '') {
                    $line[$key] = null;
                }
            }

            return $line;
        }, $lines);
    }
}
