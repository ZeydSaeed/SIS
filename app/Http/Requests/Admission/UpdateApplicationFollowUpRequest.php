<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateApplicationFollowUpRequest extends FormRequest
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
            'updates' => ['required', 'array', 'min:1', 'max:200'],
            'updates.*.application_id' => ['required', 'integer', 'min:1'],
            'updates.*.full_name' => ['nullable', 'string', 'max:500'],
            'updates.*.rejection_reason' => ['nullable', 'string', 'max:2000'],
            'updates.*.withdrawal_reason' => ['nullable', 'string', 'max:2000'],
            'updates.*.update_rejection_reason' => ['sometimes', 'boolean'],
            'updates.*.update_withdrawal_reason' => ['sometimes', 'boolean'],
        ];
    }
}
