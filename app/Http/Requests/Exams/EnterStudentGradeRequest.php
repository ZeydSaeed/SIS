<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class EnterStudentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentGradeRecord::class) ?? false;
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
