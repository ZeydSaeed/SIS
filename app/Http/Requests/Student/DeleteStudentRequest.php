<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «حذف الطالب» — same right as changing a student's status. */
final class DeleteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', StudentRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
