<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationJobsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCommunication') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'job_status' => ['nullable', 'integer', 'in:1,2,3'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
