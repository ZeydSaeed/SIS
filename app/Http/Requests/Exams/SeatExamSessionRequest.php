<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Seat a class (or one of its sections) into an exam session — exam.enrollment.create. */
class SeatExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createEnrollment', ExamRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'enrollment_ids' => ['prohibited'],
            'academic_year_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
