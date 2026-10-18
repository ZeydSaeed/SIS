<?php

namespace App\Http\Requests\Documents;

use App\Domain\Documents\Support\DocumentEntityTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentsByEntityRequest extends FormRequest
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
            'entity_type' => ['required', 'string', 'max:50', Rule::notIn(DocumentEntityTypes::MODULE_OWNED)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
