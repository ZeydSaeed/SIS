<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class TransferApplicationRequest extends FormRequest
{
    use ValidatesApplicationSchool;

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
            'target_school_id' => $this->applicationSchoolRule(),
            'request_kind' => ['required', 'integer', 'in:1,2'],
            'application_period_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->applicationSchoolMessages();
    }
}
