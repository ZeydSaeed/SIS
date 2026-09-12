<?php

namespace App\Http\Requests\Communication;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationTemplatesRequest extends FormRequest
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
            'active_only' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
