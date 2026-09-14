<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RestorePaymentRequest extends FormRequest
{
    use RequiresFinanceIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageFinance') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'amount' => ['prohibited'],
            'voided_at' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
