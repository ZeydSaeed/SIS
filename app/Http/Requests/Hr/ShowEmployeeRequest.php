<?php

namespace App\Http\Requests\Hr;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHr') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
