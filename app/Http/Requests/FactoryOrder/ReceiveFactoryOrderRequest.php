<?php

namespace App\Http\Requests\FactoryOrder;

use App\Enums\FactoryOrderDestination;
use App\Models\FactoryOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveFactoryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('factory_order');

        return $order instanceof FactoryOrder && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination' => ['required', Rule::enum(FactoryOrderDestination::class)],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destination.required' => 'Stoğun ekleneceği yeri seçin.',
            'quantity.required' => 'Teslim alınan adet zorunludur.',
            'quantity.min' => 'Teslim alınan adet en az 1 olmalıdır.',
        ];
    }
}
