<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterApplicationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'integer', 'min:1'],
            'file_name' => ['required', 'string', 'max:255'],
            'storage_key' => ['nullable', 'string', 'max:500'],
            'file_hash' => ['nullable', 'string', 'max:64'],
        ];
    }
}
