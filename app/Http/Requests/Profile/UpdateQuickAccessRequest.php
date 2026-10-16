<?php

namespace App\Http\Requests\Profile;

use App\Support\QuickAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuickAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quick_access' => ['present', 'array', 'max:'.QuickAccess::MAX_ITEMS],
            'quick_access.*' => ['string', Rule::in(QuickAccess::keys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quick_access.max' => 'Hızlı erişime en fazla '.QuickAccess::MAX_ITEMS.' sayfa ekleyebilirsiniz.',
        ];
    }
}
