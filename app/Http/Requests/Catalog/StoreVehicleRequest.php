<?php

namespace App\Http\Requests\Catalog;

use App\Enums\VehicleDocument;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'license_plate' => ['nullable', 'string', 'max:32', Rule::unique('vehicles', 'license_plate')],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_delivery_vehicle' => ['sometimes', 'boolean'],
            'casco_expires_on' => ['nullable', 'date'],
            'insurance_expires_on' => ['nullable', 'date'],
            'inspection_due_on' => ['nullable', 'date'],
            VehicleDocument::Casco->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            VehicleDocument::TrafficInsurance->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            VehicleDocument::Registration->value => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
