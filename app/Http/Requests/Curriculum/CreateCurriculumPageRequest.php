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
            'section_id' => ['required', 'integer', 'min:1'],
        ]);
    }
}
