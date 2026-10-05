<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Add / edit a department (الاختصاص) in a branch of the current school. */
class SaveDepartmentRequest extends FormRequest
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
            'branch_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'code' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'اسم الاختصاص مطلوب.',
            'name.max' => 'اسم الاختصاص طويل جداً.',
            'branch_id.required' => 'الفرع مطلوب.',
        ];
    }
}
