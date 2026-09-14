<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamRequest extends FormRequest
{
    use RequiresExamIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('update', ExamRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d'],
            'exam_type_id' => ['sometimes', 'integer', 'min:1'],
            'term_id' => ['sometimes', 'integer', 'min:1'],
            'target_status' => ['sometimes', 'integer', 'in:2,3,4'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'academic_year_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
