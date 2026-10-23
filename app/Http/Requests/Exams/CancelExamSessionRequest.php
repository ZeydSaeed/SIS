<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Cancel one exam session — exam.session.update, the permission CancelExamSession checks (HD-7.2-003). */
class CancelExamSessionRequest extends FormRequest
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
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'exam_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
