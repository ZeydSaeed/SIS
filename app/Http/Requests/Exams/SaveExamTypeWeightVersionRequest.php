<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** A new per-year weight version of an exam type (exam.type.manage); a written reason is required. */
class SaveExamTypeWeightVersionRequest extends FormRequest
{
    use RequiresExamIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTypes', ExamRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'weight_percentage' => ['required', 'integer', 'between:0,100'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'school_id' => ['prohibited'],
            'id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
