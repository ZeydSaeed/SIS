<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class CreateApplicationDraftRequest extends FormRequest
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
            'application_period_id' => ['required', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', 'integer', 'in:1,2'],
            'grade_level_id' => ['required', 'integer', 'min:1'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
