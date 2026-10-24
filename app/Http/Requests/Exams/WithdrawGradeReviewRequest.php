<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Withdraw an own open review — grades.review.request. */
class WithdrawGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestReview', StudentGradeRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
