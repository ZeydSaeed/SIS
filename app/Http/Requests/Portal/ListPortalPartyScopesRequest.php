<?php

namespace App\Http\Requests\Portal;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListPortalPartyScopesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePortalScopes') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'user_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
