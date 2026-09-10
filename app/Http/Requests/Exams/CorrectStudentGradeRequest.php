<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CorrectStudentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grade = StudentGradeRecord::query()->find((int) $this->route('grade'));

        return $grade !== null && ($this->user()?->can('correct', $grade) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'score' => ['nullable', 'numeric'],
            'is_absent' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'student_id' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
            'subject_id' => ['prohibited'],
            'exam_session_id' => ['prohibited'],
            'exam_enrollment_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
