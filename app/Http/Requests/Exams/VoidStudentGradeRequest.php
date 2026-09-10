<?php

namespace App\Http\Requests\Exams;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class VoidStudentGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $grade = StudentGradeRecord::query()->find((int) $this->route('grade'));

        return $grade !== null && ($this->user()?->can('void', $grade) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
