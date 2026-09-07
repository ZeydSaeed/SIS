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
        return array_merge([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'integer', 'in:1,2'],
            'birth_date' => ['required', 'date', 'before:today'],
            'student_code' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:50'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
