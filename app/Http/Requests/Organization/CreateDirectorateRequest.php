<?php

namespace App\Http\Requests\Organization;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class CreateDirectorateRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('manageDirectorates')) {
            return false;
        }

        // Placing schools changes those schools — needs the school permission too.
        return $this->input('school_ids', []) === [] || $user->can('manageSchools');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'integer', 'in:1,2,3'],
            'school_ids' => ['sometimes', 'array', 'max:500'],
            'school_ids.*' => ['integer', 'min:1', 'distinct'],
            'code' => ['prohibited'],
            'ministry_id' => ['prohibited'],
        ],
            // «الحالة» (نشط / غير نشط / مؤرشف) is part of this form: validated above (in:1,2,3) and
            // checked by the domain guard — every other sensitive field stays prohibited.
            Arr::except(SecuritySensitiveFieldGuard::prohibitedRules(), ['status']),
        );
    }
}
