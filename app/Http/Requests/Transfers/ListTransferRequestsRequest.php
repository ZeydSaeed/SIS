<?php

namespace App\Http\Requests\Transfers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListTransferRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewTransfers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['sometimes', 'integer', 'min:1'],
            'request_status' => ['sometimes', 'integer', 'in:1,2,3,4,5'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
