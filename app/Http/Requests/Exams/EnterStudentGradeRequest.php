<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class EnterStudentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentGradeRecord::class) ?? false;
    }

    /** Seat scope is checked once the seat id is known to be well-formed (invalid input stays a 422). */
    protected function passedValidation(): void
    {
        $seatId = (int) $this->validated('exam_enrollment_id');
        if ($this->user()?->can('enterSeat', [StudentGradeRecord::class, $seatId]) ?? false) {
            return;
        }

        app(SecurityAuditLoggerInterface::class)->record(
            SecurityEventType::IdorBlocked,
            'grades.enter.scope',
            'denied',
            $this->user(),
            "exam_enrollment:{$seatId}",
        );

        $this->failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'exam_enrollment_id' => ['required', 'integer', 'min:1'],
            'score' => ['nullable', 'numeric'],
            'is_absent' => ['required', 'boolean'],
            'academic_year_id' => ['prohibited'],
            'student_id' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
            'subject_id' => ['prohibited'],
            'exam_session_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
