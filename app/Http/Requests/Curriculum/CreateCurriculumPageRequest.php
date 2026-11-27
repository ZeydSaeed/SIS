<?php

namespace App\Http\Requests\Curriculum;

/**
 * «المناهج» page: a curriculum names its full place — الفرع، الاختصاص، الصف، الشعبة (owner, 2026-11-27).
 * The v1 API keeps CreateCurriculumRequest (place optional) so existing callers keep working.
 */
class CreateCurriculumPageRequest extends CreateCurriculumRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'branch_id' => ['required', 'integer', 'min:1'],
            'department_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            // The section is chosen per teacher (owner, 2026-11-27); a curriculum may still name one.
            'section_id' => ['nullable', 'integer', 'min:1'],
            // «عدد المواد» is stated on the page (owner, 2026-11-28).
            'planned_subject_count' => ['required', 'integer', 'between:1,200'],
            // The teachers of each subject: the whole subject (subject_type null) or one part (نظري، عملي …);
            // teacher_role 1 = main, 2 = assistant. Not `role` (a guarded security field).
            'teachers' => ['sometimes', 'array', 'max:400'],
            'teachers.*.subject_id' => ['required', 'integer', 'min:1'],
            'teachers.*.subject_type' => ['nullable', 'integer', 'in:1,2,3,4'],
            'teachers.*.teacher_id' => ['required', 'integer', 'min:1'],
            'teachers.*.teacher_role' => ['required', 'integer', 'in:1,2'],
            // The section taught; null = all sections of the class.
            'teachers.*.section_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
