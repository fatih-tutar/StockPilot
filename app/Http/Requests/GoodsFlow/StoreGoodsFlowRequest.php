<?php

namespace App\Http\Requests\GoodsFlow;

use App\Models\GoodsFlow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGoodsFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsFlow::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach (['store_incoming', 'store_outgoing', 'warehouse_incoming', 'warehouse_outgoing'] as $field) {
            $value = str_replace(',', '.', trim((string) $this->input($field)));
            $values[$field] = $value === '' ? '0' : $value;
        }

        $this->merge($values);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recorded_on' => ['required', 'date'],
            'store_incoming' => $this->weight(),
            'store_outgoing' => $this->weight(),
            'warehouse_incoming' => $this->weight(),
            'warehouse_outgoing' => $this->weight(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recorded_on.required' => 'Tarih zorunludur.',
            'recorded_on.date' => 'Tarih geçerli olmalıdır.',
            'store_incoming.numeric' => 'Çağlayan gelen sayı olmalıdır.',
            'store_outgoing.numeric' => 'Çağlayan giden sayı olmalıdır.',
            'warehouse_incoming.numeric' => 'Alkop gelen sayı olmalıdır.',
            'warehouse_outgoing.numeric' => 'Alkop giden sayı olmalıdır.',
            'store_incoming.min' => 'Çağlayan gelen negatif olamaz.',
            'store_outgoing.min' => 'Çağlayan giden negatif olamaz.',
            'warehouse_incoming.min' => 'Alkop gelen negatif olamaz.',
            'warehouse_outgoing.min' => 'Alkop giden negatif olamaz.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('recorded_on')) {
                return;
            }

            $companyId = $this->user()?->company_id;
            $taken = GoodsFlow::withTrashed()
                ->whereDate('recorded_on', $this->input('recorded_on'))
                ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
                ->when($this->ignoreId() !== null, fn ($query) => $query->whereKeyNot($this->ignoreId()))
                ->exists();

            if ($taken) {
                $validator->errors()->add('recorded_on', 'Bu tarihle zaten bir kayıt var, onu düzenleyebilirsiniz.');
            }
        });
    }

    protected function ignoreId(): ?int
    {
        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function weight(): array
    {
        return ['required', 'numeric', 'min:0', 'max:9999999.999'];
    }
}
