<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'student_fee_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method' => ['required', 'integer', 'in:1,2,3,9'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'school_id' => ['prohibited'],
            'refund' => ['prohibited'],
            'void' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
