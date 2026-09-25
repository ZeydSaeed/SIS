<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterStudentViaAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'application_period_id' => ['required', 'integer', 'min:1'],
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['required', 'string', 'max:100'],
            'grandfather_name' => ['required', 'string', 'max:100'],
            'great_grandfather_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mother_name' => ['required', 'string', 'max:100'],
            'maternal_father_name' => ['required', 'string', 'max:100'],
            'maternal_grandfather_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'birth_place' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'integer', 'in:1,2'],
            'target_school_id' => ['required', 'integer', 'min:1'],
            'intended_grade_name' => ['nullable', 'string', 'max:100'],
            'grade_level_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'department_name' => ['nullable', 'string', 'max:100'],
            'specialization_id' => ['nullable', 'integer', 'min:1'],
            'specialization_name' => ['nullable', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'administrative_unit' => ['nullable', 'integer', 'in:1,2,3'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'student_mobile' => ['nullable', 'string', 'max:20'],
            'guardian_mobile' => ['nullable', 'string', 'max:20'],
            'previous_school_name' => ['nullable', 'string', 'max:255'],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'previous_gpa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'previous_study_track' => ['nullable', 'integer', 'in:1,2,3,4,5,6'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
