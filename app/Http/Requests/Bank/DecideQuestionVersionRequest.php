<?php

namespace App\Http\Requests\Bank;

use App\Domain\Bank\Services\QuestionReviewRules;
use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideQuestionVersionRequest extends FormRequest
{
    use RequiresBankIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('approve', BankQuestionRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'decision' => ['required', 'string', Rule::in([QuestionReviewRules::APPROVE, QuestionReviewRules::REJECT])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn (): bool => $this->input('decision') === QuestionReviewRules::REJECT)],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'decided_by' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
