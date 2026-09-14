<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamEnrollmentRequest extends FormRequest
{
    use RequiresExamIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('updateEnrollment', ExamRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(SecuritySensitiveFieldGuard::prohibitedRules(), [
            'status' => ['sometimes', 'integer', 'in:2,3,4'],
            'seat_number' => ['sometimes', 'nullable', 'string', 'max:32'],
            'school_id' => ['prohibited'],
            'exam_session_id' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
        ]);
    }
}