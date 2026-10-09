<?php

namespace App\Http\Requests\Teachers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            // With academic_year_id, نوع التعيين of that school/year membership is updated too.
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
            'employment_type' => ['nullable', 'integer', 'between:1,5'],
            // Personal lesson limits of that membership (replaced together; empty = no limit).
            'weekly_lessons_min' => ['nullable', 'integer', 'between:0,60'],
            'weekly_lessons_max' => ['nullable', 'integer', 'between:1,60'],
            'daily_lessons_max' => ['nullable', 'integer', 'between:1,12'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'specialization_field' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date', 'before_or_equal:today', 'after:1950-01-01'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            // «اللقب العلمي» / «الاختصار» (replaced together when sent).
            'academic_title_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'abbreviation' => ['sometimes', 'nullable', 'string', 'max:20'],
            'employee_code' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'full_name' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
