<?php

namespace App\Http\Requests\Bank;

use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionDraftRequest extends FormRequest
{
    use RequiresBankIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('author', BankQuestionRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'subject_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
        ], QuestionContentRules::rules(), SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
