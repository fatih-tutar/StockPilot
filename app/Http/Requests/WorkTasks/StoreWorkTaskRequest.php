<?php

namespace App\Http\Requests\WorkTasks;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkTask::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'repeats_monthly' => $this->boolean('repeats_monthly'),
            'due_on' => $this->input('due_on') ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
            'repeats_monthly' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Görev tanımı zorunludur.',
            'due_on.required' => 'Termin tarihi zorunludur.',
        ];
    }
}
