<?php

namespace App\Http\Requests\Organization;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListDepartmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EnrollmentRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(SecuritySensitiveFieldGuard::prohibitedRules(), [
            'branch_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);
    }
}
