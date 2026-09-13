<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateSubjectRequest extends FormRequest
{
    use RequiresCurriculumIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['required', 'integer', 'in:1,2,3'],
            'credit_hours' => ['nullable', 'integer', 'min:0', 'max:40'],
            'max_grade' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'pass_grade' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'status' => ['prohibited'],
            'subject_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
