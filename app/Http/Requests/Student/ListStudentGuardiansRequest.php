<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListStudentGuardiansRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Class-level viewAny — avoid Eloquent student resolve under RLS; handler scopes by school.
        return $this->user()?->can('viewAny', StudentRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
