<?php

namespace App\Http\Requests\WorkTasks;

use App\Enums\WorkTaskStatus;
use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('work_task');

        return $task instanceof WorkTask && ($this->user()?->can('update', $task) ?? false);
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
            'status' => ['required', Rule::enum(WorkTaskStatus::class)],
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
