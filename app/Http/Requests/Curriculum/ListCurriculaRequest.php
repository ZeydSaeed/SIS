<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListCurriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCurriculum') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
