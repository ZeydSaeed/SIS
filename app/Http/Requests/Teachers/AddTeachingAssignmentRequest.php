<?php

namespace App\Http\Requests\Teachers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** The teacher teaches a subject in a branch (+ optional department, class, section). */
class AddTeachingAssignmentRequest extends FormRequest
{
    use RequiresTeacherIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTeachers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'class_id' => ['nullable', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
