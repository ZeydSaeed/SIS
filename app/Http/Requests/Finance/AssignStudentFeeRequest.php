<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class AssignStudentFeeRequest extends FormRequest
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
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'fee_type_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'amount' => ['sometimes', 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'school_id' => ['prohibited'],
            'payment' => ['prohibited'],
            'paid_at' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
