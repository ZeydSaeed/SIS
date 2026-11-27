<?php

namespace App\Http\Requests\Bank;

use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Submitting a draft or starting a new version: no body, author permission. */
class AuthorQuestionActionRequest extends FormRequest
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
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
