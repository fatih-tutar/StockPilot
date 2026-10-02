<?php

namespace App\Http\Requests\Visits;

use App\Models\CustomerVisit;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CustomerVisit::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_visit_category_id' => $this->input('customer_visit_category_id') ?: null,
            'visited_on' => $this->input('visited_on') ?: null,
            'planned_on' => $this->input('planned_on') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'customer_visit_category_id' => ['nullable', 'integer', 'exists:customer_visit_categories,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'visited_on' => ['nullable', 'date'],
            'planned_on' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Müşteri adı zorunludur.',
        ];
    }
}
