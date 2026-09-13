<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListStudentDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = StudentRecord::query()->find($this->route('student'));
        if ($student === null) {
            return false;
        }

        return $this->user()?->can('view', $student) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
