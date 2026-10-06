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
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'specialization_field' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'employee_code' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'full_name' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
