<?php

namespace App\Http\Requests\Teachers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «حذف المدرس» — same right as deactivating a teacher. */
class DeleteTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeachers') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
