<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeApplicationPeriodStatusRequest extends FormRequest
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
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'integer', Rule::in([0, 1, 2])],
        ];
    }
}
