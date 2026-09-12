<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListStudentFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewFinance') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'enrollment_id' => ['sometimes', 'integer', 'min:1'],
            'academic_year_id' => ['sometimes', 'integer', 'min:1'],
            'fee_status' => ['sometimes', 'integer', 'in:1,2,3,4'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
