<?php

namespace App\Http\Requests\Hr;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RegisterEmployeeRequest extends FormRequest
{
    use RequiresHrIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageHr') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'employee_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:20'],
            'hire_date' => ['sometimes', 'nullable', 'date'],
            'job_position_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'teacher_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
            'user_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
