<?php

namespace App\Http\Requests\CustomOrders;

use App\Enums\CustomOrderStatus;
use App\Enums\DeliveryMethod;
use App\Models\CustomOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomOrder::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->input('notes') === '' ? null : $this->input('notes'),
            'items' => $this->normalizedItems(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedItems(): array
    {
        $items = $this->input('items', []);
        if (! is_array($items)) {
            return [];
        }

        return array_map(function (mixed $item): array {
            if (! is_array($item)) {
                return [];
            }

            foreach (['factory_id', 'length', 'due_on', 'id'] as $field) {
                if (($item[$field] ?? null) === '') {
                    $item[$field] = null;
                }
            }

            return $item;
        }, $items);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)],
            'status' => ['required', Rule::enum(CustomOrderStatus::class)],
            'notes' => ['nullable', 'string'],
            'ordered_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.length' => ['nullable', 'string', 'max:100'],
            'items.*.factory_id' => ['nullable', 'integer', 'exists:factories,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.due_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Müşteri seçin.',
            'items.required' => 'En az bir kalem ekleyin.',
            'items.min' => 'En az bir kalem ekleyin.',
            'items.*.product_name.required' => 'Ürün adı zorunludur.',
        ];
    }
}
