<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class EnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EnrollmentRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'student_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
