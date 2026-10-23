<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Create or change an exam type of the school in context (exam.type.manage). */
class SaveExamTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'weight_percentage' => ['required', 'integer', 'between:0,100'],
            'school_id' => ['prohibited'],
            'id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
