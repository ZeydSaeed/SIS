<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class CreateSchoolRequest extends FormRequest
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
            'directorate_id' => ['required', 'integer', 'min:1'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'integer', 'in:1,2,3'],
            'code' => ['prohibited'],
            'school_type' => ['prohibited'],
        ],
            // «الحالة» (نشط / غير نشط / مؤرشف) is part of this form: validated above (in:1,2,3) and
            // checked by the domain guard — every other sensitive field stays prohibited.
            Arr::except(SecuritySensitiveFieldGuard::prohibitedRules(), ['status']),
        );
    }
}
