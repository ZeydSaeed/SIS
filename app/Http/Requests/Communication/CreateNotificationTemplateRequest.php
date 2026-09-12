<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateNotificationTemplateRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'integer', 'in:1,2,3,9'],
            'subject_template' => ['sometimes', 'nullable', 'string'],
            'body_template' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
            'recipient' => ['prohibited'],
            'send' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
