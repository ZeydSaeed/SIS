<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Open a review of one grade — grades.review.request. */
class OpenGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestReview', StudentGradeRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'grade_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
