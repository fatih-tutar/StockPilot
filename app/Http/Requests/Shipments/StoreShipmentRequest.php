<?php

namespace App\Http\Requests\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
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
            'quote_id' => ['nullable', 'integer', 'exists:quotes,id'],
            'status' => ['required', Rule::enum(ShipmentStatus::class)],
            'ship_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:ship_date'],
            'vehicle_plate' => ['nullable', 'string', 'max:32'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity_piece' => ['required', 'integer', 'min:0'],
            'items.*.quantity_pallet' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'En az bir kalem ekleyin.',
            'items.min' => 'En az bir kalem ekleyin.',
            'items.*.description.required' => 'Kalem açıklaması zorunludur.',
        ];
    }
}
