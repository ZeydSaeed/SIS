<?php

namespace App\Http\Requests\Communication;

use App\Domain\Communication\Support\MessageRecipientTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCommunication') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'recipient_type' => ['sometimes', 'string', Rule::in(MessageRecipientTypes::ALLOWED)],
            'recipient_id' => ['sometimes', 'integer', 'min:1'],
            'message_status' => ['sometimes', 'integer', 'in:1,2,3'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
