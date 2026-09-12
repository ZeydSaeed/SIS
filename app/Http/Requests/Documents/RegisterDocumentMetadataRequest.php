<?php

namespace App\Http\Requests\Documents;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDocumentMetadataRequest extends FormRequest
{
    use RequiresDocumentIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageDocuments') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'entity_type' => ['required', 'string', 'max:50'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'document_type' => ['required', 'integer', 'in:1,2,3,4,9'],
            'storage_key' => ['required', 'string', 'max:500'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:0'],
            'file_hash' => ['required', 'string', 'size:64'],
            'school_id' => ['prohibited'],
            'file' => ['prohibited'],
            'content' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
