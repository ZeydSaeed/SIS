<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class AssignEnrollmentSubjectRequest extends FormRequest
{
    use RequiresEnrollmentIdempotencyKey;

    public function authorize(): bool
    {
        $enrollment = EnrollmentRecord::query()->find($this->route('enrollment'));
        if ($enrollment === null) {
            return $this->user()?->can('update', EnrollmentRecord::class) ?? false;
        }

        return $this->user()?->can('update', $enrollment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'subject_id' => ['required', 'integer', 'min:1'],
            'is_elective' => ['nullable', 'boolean'],
            'enrollment_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
