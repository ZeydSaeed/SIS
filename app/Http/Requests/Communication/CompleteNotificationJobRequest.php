<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CompleteNotificationJobRequest extends FormRequest
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
            'sent_count' => ['required', 'integer', 'min:0'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
