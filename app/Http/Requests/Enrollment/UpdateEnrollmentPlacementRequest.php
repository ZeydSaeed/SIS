<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()?->can('update', $enrollment instanceof EnrollmentRecord
            ? $enrollment
            : EnrollmentRecord::query()->find($enrollment)) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['required', 'integer', 'min:1'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'clear_effective_to' => ['sometimes', 'boolean'],
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
            'stage_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'integer', 'in:1,2'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
