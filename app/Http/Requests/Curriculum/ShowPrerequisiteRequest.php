<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowPrerequisiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewCurriculum') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
