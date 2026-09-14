<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSessionRequest extends FormRequest
{
    use RequiresExamIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('updateSession', ExamRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'session_date' => ['sometimes', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'date_format:H:i:s'],
            'end_time' => ['sometimes', 'date_format:H:i:s'],
            'room_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_grade' => ['sometimes', 'integer', 'min:1'],
            'pass_grade' => ['sometimes', 'integer', 'min:0'],
            'school_id' => ['prohibited'],
            'exam_id' => ['prohibited'],
            'subject_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
