<?php

namespace App\Http\Requests\Enrollment;

use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «الاختصار واللون» of a class / section — same holders as «الصفوف والشعب» editing. */
class UpdateStructureAppearanceRequest extends FormRequest
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
            'target' => ['required', 'string', 'in:class,section'],
            'id' => ['required', 'integer', 'min:1'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
