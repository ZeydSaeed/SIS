<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class ConvertApplicationRequest extends FormRequest
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
        return [];
    }
}
