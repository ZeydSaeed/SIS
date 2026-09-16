<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentId = $this->route('student');
        if (! is_numeric($studentId)) {
            return false;
        }

        $record = StudentRecord::query()->find((int) $studentId);

        return $this->user()?->can('update', $record) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = StudentProfileRules::fields(requireStudentCode: false);
        unset($rules['student_code']);

        return array_merge($rules, SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
