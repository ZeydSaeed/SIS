<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CancelEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $this->user()?->can('cancel', $enrollment instanceof EnrollmentRecord
            ? $enrollment
            : EnrollmentRecord::query()->find($enrollment)) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'effective_to' => ['nullable', 'date'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
