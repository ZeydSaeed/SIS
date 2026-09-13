<?php

namespace App\Http\Requests\Documents;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewDocuments') ?? false;
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
