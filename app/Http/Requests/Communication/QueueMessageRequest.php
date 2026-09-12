<?php

namespace App\Http\Requests\Communication;

use App\Domain\Communication\Support\MessageRecipientTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueMessageRequest extends FormRequest
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
            'recipient_type' => ['required', 'string', Rule::in(MessageRecipientTypes::ALLOWED)],
            'recipient_id' => ['required', 'integer', 'min:1'],
            'channel' => ['required', 'integer', 'in:1,2,3,9'],
            'subject' => ['sometimes', 'nullable', 'string'],
            'body' => ['required', 'string'],
            'template_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'send' => ['prohibited'],
            'sent_at' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
