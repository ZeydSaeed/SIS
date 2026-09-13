<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class MarkMessageSentRequest extends FormRequest
{
    use RequiresCommunicationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageCommunication') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'sent_at' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
