<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class BulkTransitionApplicationStatusRequest extends FormRequest
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
            'application_ids' => ['required', 'array', 'min:1', 'max:200'],
            'application_ids.*' => ['required', 'integer', 'min:1'],
            'to_status' => ['required', 'integer', 'between:1,8'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
