<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class DownloadDocumentRequest extends FormRequest
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
        return [];
    }
}
