<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Add / edit a branch (الفرع) of the current school. */
class SaveBranchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'school_id' => ['prohibited'],
            'code' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفرع مطلوب.',
            'name.max' => 'اسم الفرع طويل جداً.',
        ];
    }
}
