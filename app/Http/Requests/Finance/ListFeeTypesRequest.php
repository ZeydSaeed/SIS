<?php

namespace App\Http\Requests\Finance;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListFeeTypesRequest extends FormRequest
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
            'fee_status' => ['sometimes', 'integer', 'in:1,2'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
