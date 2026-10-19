<?php

namespace App\Http\Requests\Leave;

use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Mark notifications read: `ids` (some) or none (all). The owner is always the signed-in user. */
class MarkLeaveNotificationsReadRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('accessLeave') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'ids' => ['nullable', 'array', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
            'school_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
