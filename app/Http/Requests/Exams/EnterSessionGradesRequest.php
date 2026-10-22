<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Grade-entry sheet save. Seat scope is decided per row by EnterSessionGradesHandler (server-side), so a
 * modified exam_enrollment_id can only produce a per-row denial, never a grade.
 */
class EnterSessionGradesRequest extends FormRequest
{
    public const MAX_ROWS = 500;

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
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*' => ['array:exam_enrollment_id,score,is_absent'],
            'rows.*.exam_enrollment_id' => ['required', 'integer', 'min:1'],
            'rows.*.score' => ['nullable', 'numeric', 'min:0', 'max:999.99', 'decimal:0,2'],
            'rows.*.is_absent' => ['required', 'boolean'],
            'academic_year_id' => ['prohibited'],
            'exam_session_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
