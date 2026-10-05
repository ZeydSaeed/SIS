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
            // School, request kind and academic year change only through the transfers page.
            'target_school_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'request_kind' => ['prohibited'],
            'application_period_id' => ['prohibited'],
            'academic_year_id' => ['prohibited'],
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
