<?php

namespace App\Http\Requests\Shipments;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Shipment $shipment */
        $shipment = $this->route('shipment');

        return $this->user()?->can('update', $shipment) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(ShipmentStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Shipment $shipment */
            $shipment = $this->route('shipment');
            $target = ShipmentStatus::from($this->string('status')->toString());

            if (! in_array($target, $shipment->status->allowedTransitions(), true)) {
                $validator->errors()->add('status', 'Bu durum geçişine izin verilmiyor.');
            }
        });
    }
}
