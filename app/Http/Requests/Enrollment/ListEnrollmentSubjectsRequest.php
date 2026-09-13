<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListEnrollmentSubjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = EnrollmentRecord::query()->find($this->route('enrollment'));
        if ($enrollment === null) {
            return $this->user()?->can('view', EnrollmentRecord::class) ?? false;
        }

        return $this->user()?->can('view', $enrollment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
