<?php

namespace App\Http\Requests\Curriculum;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «حذف» of a subject, curriculum, curriculum line or prerequisite (safe delete). */
class DeleteCurriculumRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageCurriculum') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
