<?php

namespace App\Http\Requests\Catalog;

use App\Enums\VehicleDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vehicle')) ?? false;
    }

    public function rules(): array
    {
        $vehicleId = $this->route('vehicle')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'license_plate' => ['nullable', 'string', 'max:32', Rule::unique('vehicles', 'license_plate')->ignore($vehicleId)],
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
