<?php

namespace App\Http\Requests\Transfers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CompleteTransferRequestRequest extends FormRequest
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
            'to_class_id' => ['required', 'integer', 'min:1'],
            'to_section_id' => ['required', 'integer', 'min:1'],
            'effective_date' => ['required', 'date'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
