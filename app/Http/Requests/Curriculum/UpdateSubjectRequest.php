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
            'subject_type' => ['sometimes', 'integer', 'in:1,2,3,4'],
            // «نوع المادة» takes several values (عملي + نظري …); subject_type alone = one type (older callers).
            'subject_types' => ['required_with:subject_type_hours', 'array', 'min:1', 'max:4'],
            'subject_types.*' => ['integer', 'in:1,2,3,4', 'distinct'],
            // Weekly hours of each chosen type (type → hours), e.g. {"3": 2, "4": 3}; sent with subject_types.
            'subject_type_hours' => ['sometimes', 'array'],
            'subject_type_hours.*' => ['nullable', 'integer', 'min:0', 'max:40'],
            'credit_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:40'],
            'max_grade' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'pass_grade' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'prerequisites_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            // «الاختصار واللون» (timetable display; null = default).
            'abbreviation' => ['sometimes', 'nullable', 'string', 'max:20'],
            'color_hue' => ['sometimes', 'nullable', 'integer', 'between:0,359'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
            'subject_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
