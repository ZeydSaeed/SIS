<?php

namespace App\Http\Requests\Teachers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class LeaveTeacherSchoolRequest extends FormRequest
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
            'school_id' => ['prohibited'],
            'teacher_id' => ['prohibited'],
            'is_primary' => ['prohibited'],
            'left_at' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
