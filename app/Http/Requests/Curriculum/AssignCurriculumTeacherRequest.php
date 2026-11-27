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
            // The part taught (one of the subject's types); absent / null = the whole subject.
            'subject_type' => ['sometimes', 'nullable', 'integer', 'in:1,2,3,4'],
            // The section taught; absent / null = all sections of the class.
            'section_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
