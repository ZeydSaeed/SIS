<?php

namespace App\Http\Requests\Organization;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reuse enrollment.view + school context for org catalog reads (no dedicated org.view yet).
        return $this->user()?->can('viewAny', EnrollmentRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(SecuritySensitiveFieldGuard::prohibitedRules(), [
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
