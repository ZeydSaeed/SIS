<?php

namespace App\Http\Requests\Teachers;

use App\Application\Teachers\Commands\ChangeTeachersStatusHandler;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** نشط / غير نشط for the selected rows. */
class ChangeTeachersStatusRequest extends FormRequest
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
            'teacher_status' => ['required', 'integer', 'in:1,2'], // «status» is a prohibited security-sensitive field name.
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
