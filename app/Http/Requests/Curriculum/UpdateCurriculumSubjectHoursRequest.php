<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «نظري» / «عملي» hours of a subject in a curriculum. */
class UpdateCurriculumSubjectHoursRequest extends FormRequest
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
            'theory_hours' => ['present', 'nullable', 'integer', 'min:0', 'max:40'],
            'practical_hours' => ['present', 'nullable', 'integer', 'min:0', 'max:40'],
            'weekly_hours' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
