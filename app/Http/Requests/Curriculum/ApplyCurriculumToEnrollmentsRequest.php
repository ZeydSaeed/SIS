<?php

namespace App\Http\Requests\Curriculum;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Same authority as assigning a subject to an enrollment (enrollment update). */
class ApplyCurriculumToEnrollmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', EnrollmentRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
