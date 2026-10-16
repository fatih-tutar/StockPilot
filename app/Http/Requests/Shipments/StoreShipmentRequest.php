<?php

namespace App\Http\Requests\Shipments;

use App\Enums\ShipmentType;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Shipment::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'ship_type' => ['required', Rule::enum(ShipmentType::class)],
            'vehicle_id' => ['nullable', 'integer', Rule::exists(Vehicle::class, 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity_piece' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Firma seçin.',
            'ship_type.required' => 'Sevk tipi seçin.',
            'items.required' => 'En az bir kalem ekleyin.',
            'items.min' => 'En az bir kalem ekleyin.',
            'items.*.product_id.required' => 'Ürün seçin.',
            'items.*.quantity_piece.required' => 'Adet zorunludur.',
            'items.*.unit_price.required' => 'Fiyat zorunludur.',
        ];
    }
}
