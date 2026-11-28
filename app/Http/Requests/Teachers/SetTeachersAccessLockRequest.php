<?php

namespace App\Http\Requests\Teachers;

use App\Application\Teachers\Commands\SetTeachersAccessLockHandler;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «قفل» / «فتح القفل» for the selected rows. */
class SetTeachersAccessLockRequest extends FormRequest
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
            'teacher_ids' => ['required', 'array', 'min:1', 'max:'.SetTeachersAccessLockHandler::MAX_TEACHERS],
            'teacher_ids.*' => ['integer', 'min:1'],
            'locked' => ['required', 'boolean'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
