<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
{
    use RequiresCurriculumIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject_type' => ['sometimes', 'integer', 'in:1,2,3'],
            'credit_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:40'],
            'max_grade' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'pass_grade' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
            'subject_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
