<?php

namespace App\Http\Requests\Audit;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListLoginHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAudit') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'user_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
