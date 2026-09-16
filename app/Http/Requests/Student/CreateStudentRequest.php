<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = StudentProfileRules::fields(requireStudentCode: true);
        unset($rules['student_code']);
        $rules['student_code'] = ['nullable', 'string', 'max:50'];

        return array_merge($rules, SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
