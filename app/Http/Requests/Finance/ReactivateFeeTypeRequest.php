<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ReactivateFeeTypeRequest extends FormRequest
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
            'status' => ['prohibited'],
            'school_id' => ['prohibited'],
            'amount' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
