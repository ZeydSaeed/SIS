<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Take a review — grades.review.decide. */
class StartGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decideReview', StudentGradeRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
