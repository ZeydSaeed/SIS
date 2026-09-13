<?php

namespace App\Http\Requests\Hr;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateEmployeeRequest extends FormRequest
{
    use RequiresHrIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageHr') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
            'school_id' => ['prohibited'],
            'effective_to' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
