<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Delete (deactivate) a branch of the current school. */
class ManageBranchStructureRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageSchools') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }
}
