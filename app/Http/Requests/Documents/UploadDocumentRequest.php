<?php

namespace App\Http\Requests\Documents;

use App\Domain\Documents\Support\DocumentEntityTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
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
        $maxKb = (int) ceil(((int) config('sis.documents.max_bytes', 10 * 1024 * 1024)) / 1024);
        /** @var list<string> $mimes */
        $mimes = config('sis.documents.allowed_mimes', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ]);

        return array_merge([
            'entity_type' => ['required', 'string', Rule::in(DocumentEntityTypes::ALLOWED)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'document_type' => ['required', 'integer', 'in:1,2,3,4,9'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $mimes)],
            'school_id' => ['prohibited'],
            'storage_key' => ['prohibited'],
            'file_hash' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
