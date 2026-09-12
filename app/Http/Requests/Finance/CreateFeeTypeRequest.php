<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateFeeTypeRequest extends FormRequest
{
    use RequiresFinanceIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageFinance') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'is_recurring' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
            'payment' => ['prohibited'],
            'student_fee_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
