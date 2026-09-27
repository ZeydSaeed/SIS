<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateApplicationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'reviewed_at' => ['nullable', 'date'],
            'update_placement' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'department_name' => ['nullable', 'string', 'max:100'],
            'grade_level_id' => ['nullable', 'integer', 'min:1'],
            'intended_grade_name' => ['nullable', 'string', 'max:100'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
            'specialization_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
