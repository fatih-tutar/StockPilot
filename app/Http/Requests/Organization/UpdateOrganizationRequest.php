<?php

namespace App\Http\Requests\Organization;

use App\Models\OrganizationMember;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OrganizationMember::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $people = $this->input('people', []);

        if (! is_array($people)) {
            return;
        }

        $this->merge([
            'people' => array_map(function (mixed $person): mixed {
                if (! is_array($person)) {
                    return $person;
                }

                $person['name'] = $person['name'] === '' ? null : ($person['name'] ?? null);
                $person['title'] = $person['title'] === '' ? null : ($person['title'] ?? null);

                return $person;
            }, $people),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'people' => ['required', 'array'],
            'people.*.id' => ['required', 'integer', 'exists:organization_members,id'],
            'people.*.name' => ['nullable', 'string', 'max:255'],
            'people.*.title' => ['nullable', 'string', 'max:255'],
            'people.*.photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'people.*.photo.mimes' => 'Fotoğraf jpg, png veya webp olmalı.',
            'people.*.photo.max' => 'Fotoğraf en fazla 5 MB olabilir.',
        ];
    }
}
