<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ReopenEnrollmentRequest extends FormRequest
{
    use RequiresEnrollmentIdempotencyKey;

    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()?->can('reopen', $enrollment instanceof EnrollmentRecord
            ? $enrollment
            : EnrollmentRecord::query()->find($enrollment)) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
            'effective_to' => ['prohibited'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
