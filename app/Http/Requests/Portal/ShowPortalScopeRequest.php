<?php

namespace App\Http\Requests\Portal;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowPortalScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePortalScopes') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
