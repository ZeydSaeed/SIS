<?php

namespace App\Http\Requests\Bank;

use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RetireQuestionRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:2000'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
