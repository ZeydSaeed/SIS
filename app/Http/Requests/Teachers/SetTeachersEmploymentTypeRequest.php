<?php

namespace App\Http\Requests\Teachers;

use App\Application\Teachers\Commands\ChangeTeachersStatusHandler;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** نوع التعيين (ملاك / مكلف / تنسيب / محاضر / عقد) for the selected rows. */
class SetTeachersEmploymentTypeRequest extends FormRequest
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
            'teacher_ids' => ['required', 'array', 'min:1', 'max:'.ChangeTeachersStatusHandler::MAX_TEACHERS],
            'teacher_ids.*' => ['integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'employment_type' => ['required', 'integer', 'between:1,5'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
