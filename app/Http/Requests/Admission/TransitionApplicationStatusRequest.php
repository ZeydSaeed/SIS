<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionApplicationStatusRequest extends FormRequest
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
        $requiresReason = in_array((int) $this->input('to_status'), [7, 8], true);

        return [
            'to_status' => ['required', 'integer', 'between:1,8'],
            'notes' => [
                Rule::requiredIf($requiresReason),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
