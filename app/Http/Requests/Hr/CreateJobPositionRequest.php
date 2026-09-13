<?php

namespace App\Http\Requests\Hr;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateJobPositionRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'integer', 'in:1,2,3,4,9'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
