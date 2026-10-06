<?php

namespace App\Http\Requests\Enrollment;

use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «الصفوف والشعب» — create (POST, academic_year_id required) or edit (PATCH) a class. */
class SaveClassRequest extends FormRequest
{
    use RequiresEnrollmentIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return app(AuthorizationServiceInterface::class)->userHasPermission($user, Permission::ENROLLMENT_UPDATE)
            && app(EnrollmentSchoolAccessService::class)->canAccessEnrollment($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => [$this->isMethod('post') ? 'required' : 'prohibited', 'integer', 'min:1'],
            'grade_level_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:'.EnrollmentStructureGuard::MAX_NAME_LENGTH],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:'.EnrollmentStructureGuard::MAX_CAPACITY],
            'school_id' => ['prohibited'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
