<?php

namespace App\Http\Requests\Academic;

use App\Http\Requests\Enrollment\RequiresEnrollmentIdempotencyKey;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateAcademicYearRequest extends FormRequest
{
    use RequiresEnrollmentIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        // Global catalog — permission + school context only (no Eloquent policy resolve).
        return app(AuthorizationServiceInterface::class)->userHasPermission($user, Permission::ENROLLMENT_CREATE)
            && app(EnrollmentSchoolAccessService::class)->canAccessEnrollment($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Override catalog fields after the shared guard (is_current/status are domain inputs here).
        return array_merge(SecuritySensitiveFieldGuard::prohibitedRules(), [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', 'min:1', 'max:32767'],
            'id' => ['prohibited'],
        ]);
    }
}
