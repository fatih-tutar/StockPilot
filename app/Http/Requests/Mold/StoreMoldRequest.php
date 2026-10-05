<?php

namespace App\Http\Requests\Mold;

use App\Enums\MoldDocument;
use App\Models\Mold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Mold::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') === '' ? null : $this->input('description'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'factory_id' => ['required', 'integer', Rule::exists('factories', 'id')->whereNull('deleted_at')],
            'number' => ['required', 'string', 'max:32'],
            'client_offer_price' => ['required', 'string', 'max:64'],
            'factory_offer_price' => ['required', 'string', 'max:64'],
            'due_on' => ['required', 'date'],
            'contact_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            MoldDocument::FactoryApproval->value => ['required', 'file', 'mimes:pdf', 'max:10240'],
            MoldDocument::ClientApproval->value => ['required', 'file', 'mimes:pdf', 'max:10240'],
            MoldDocument::Contract->value => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Müşteri seçin.',
            'factory_id.required' => 'Fabrika seçin.',
            'number.required' => 'Kalıp numarası zorunludur.',
            'client_offer_price.required' => 'Firmaya verilen teklif zorunludur.',
            'factory_offer_price.required' => 'Fabrikadan alınan teklif zorunludur.',
            'due_on.required' => 'Termin tarihi zorunludur.',
            'contact_name.required' => 'İlgili kişi zorunludur.',
            'factory_approval.required' => 'Fabrika PDF dosyası yüklenmelidir.',
            'client_approval.required' => 'Firma PDF dosyası yüklenmelidir.',
            'contract.required' => 'Sözleşme PDF dosyası yüklenmelidir.',
        ];
    }
}
