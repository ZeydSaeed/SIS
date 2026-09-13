<?php

namespace App\Http\Requests\Audit;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RegisterAuditLogRequest extends FormRequest
{
    use RequiresAuditIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageAudit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'action' => ['required', 'string', 'max:50'],
            'entity_type' => ['required', 'string', 'max:50'],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['nullable', 'array'],
            'school_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'ip_address' => ['prohibited'],
            'correlation_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
