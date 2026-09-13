<?php

namespace App\Http\Requests\Teachers;

use App\Security\Authorization\TeachersSchoolAccessService;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class AssignTeacherSchoolRequest extends FormRequest
{
    use RequiresTeacherIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('manageTeachers')) {
            return false;
        }

        $sourceSchoolId = (int) $this->input('source_school_id');
        if ($sourceSchoolId < 1) {
            return true; // let validation fail the field
        }

        return app(TeachersSchoolAccessService::class)->canAccessSchoolId($user, $sourceSchoolId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'source_school_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'is_primary' => ['prohibited'],
            'teacher_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
