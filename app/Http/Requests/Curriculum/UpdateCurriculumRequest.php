<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCurriculumRequest extends FormRequest
{
    use RequiresCurriculumIdempotencyKey {
        withValidator as idempotencyWithValidator;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'string', 'max:255'],
            'specialization_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'academic_year_id' => ['prohibited'],
            'grade_level_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $this->idempotencyWithValidator($validator);
        $validator->after(function (Validator $validator): void {
            if (! $this->exists('name') && ! $this->exists('specialization_id')) {
                $validator->errors()->add('fields', 'At least one of name or specialization_id is required.');
            }
        });
    }
}
