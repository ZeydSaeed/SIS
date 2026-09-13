<?php

namespace App\Http\Requests\Audit;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RecordLoginHistoryRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'login_status' => ['required', 'integer', 'in:1,2'],
            'school_id' => ['prohibited'],
            'ip_address' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
