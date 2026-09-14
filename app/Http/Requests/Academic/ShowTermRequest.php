<?php

namespace App\Http\Requests\Academic;

use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return app(AuthorizationServiceInterface::class)->userHasPermission($user, Permission::ENROLLMENT_VIEW)
            && app(EnrollmentSchoolAccessService::class)->canAccessEnrollment($user);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
