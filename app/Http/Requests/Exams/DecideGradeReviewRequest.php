<?php

namespace App\Http\Requests\Exams;

use App\Application\Exams\Commands\DecideGradeReviewCommand;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Decide a review — grades.review.decide. */
class DecideGradeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('decideReview', StudentGradeRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'decision' => ['required', 'string', Rule::in([DecideGradeReviewCommand::ACCEPT, DecideGradeReviewCommand::REJECT])],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'score' => ['nullable', 'numeric', 'min:0'],
            'is_absent' => ['nullable', 'boolean'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
