<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «المدرسون» of a curriculum subject: teacher_role 1 = مدرس المادة (الرئيسي), 2 = مدرس مساعد. */
class AssignCurriculumTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'teacher_id' => ['required', 'integer', 'min:1'],
            // Not `role`: that name is a guarded security field (SecuritySensitiveFieldGuard).
            'teacher_role' => ['required', 'integer', 'in:1,2'],
            'replace_main' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
