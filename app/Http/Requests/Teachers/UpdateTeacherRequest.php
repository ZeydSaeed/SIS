<?php

namespace App\Http\Requests\Teachers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    use RequiresTeacherIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTeachers') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'specialization_field' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'employee_code' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'full_name' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
