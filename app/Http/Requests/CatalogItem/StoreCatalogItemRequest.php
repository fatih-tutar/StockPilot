<?php

namespace App\Http\Requests\CatalogItem;

use App\Enums\CatalogImage;
use App\Models\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CatalogItem::class) ?? false;
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
        return [
            'product_code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('catalog_items', 'product_code')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.code' => ['nullable', 'string', 'max:32'],
            'lines.*.model' => ['required', 'string', 'max:128'],
            'lines.*.quantity' => ['nullable', 'string', 'max:64'],
            'lines.*.price' => ['nullable', 'string', 'max:64'],
            CatalogImage::Primary->value => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            CatalogImage::Secondary->value => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
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
            'image_1.required' => 'Birinci fotoğraf yüklenmelidir.',
            'image_2.required' => 'İkinci fotoğraf yüklenmelidir.',
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

            foreach (['code', 'model', 'quantity', 'price'] as $key) {
                if (($line[$key] ?? null) === '') {
                    $line[$key] = null;
                }
            }

            return $line;
        }, $lines);
    }
}
