<?php

namespace App\Http\Requests\Transfers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequestRequest extends FormRequest
{
    use RequiresTransferIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTransfers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'to_school_id' => ['required', 'integer', 'min:1'],
            'from_enrollment_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'from_school_id' => ['prohibited'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
