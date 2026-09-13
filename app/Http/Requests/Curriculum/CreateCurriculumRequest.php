<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateCurriculumRequest extends FormRequest
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
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'grade_level_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'school_id' => ['prohibited'],
            'specialization_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
