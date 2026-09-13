<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateNotificationJobRequest extends FormRequest
{
    use RequiresCommunicationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunication') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'template_id' => ['required', 'integer', 'min:1'],
            'target_filter' => ['required', 'array', 'min:1'],
            'total_count' => ['required', 'integer', 'min:0'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'sent_count' => ['prohibited'],
            'job_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
