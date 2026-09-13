<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class LinkCurriculumSubjectRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', 'min:1'],
            'weekly_hours' => ['nullable', 'integer', 'min:0', 'max:40'],
            'is_required' => ['nullable', 'boolean'],
            'subject_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'curriculum_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
