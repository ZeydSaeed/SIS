<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCurriculumSpecializationRequest extends FormRequest
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
            'specialization_id' => ['present', 'nullable', 'integer', 'min:1'],
            'name' => ['prohibited'],
            'academic_year_id' => ['prohibited'],
            'grade_level_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
