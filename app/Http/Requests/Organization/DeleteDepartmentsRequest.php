<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Delete (deactivate) the selected departments of the current school. */
class DeleteDepartmentsRequest extends FormRequest
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
            'department_ids' => ['required', 'array', 'min:1', 'max:200'],
            'department_ids.*' => ['integer', 'min:1'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'department_ids.required' => 'اختر اختصاصاً واحداً على الأقل.',
        ];
    }
}
