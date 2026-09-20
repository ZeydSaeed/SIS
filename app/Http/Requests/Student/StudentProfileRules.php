<?php

namespace App\Http\Requests\Student;

use App\Domain\Student\ValueObjects\StudentReligion;

final class StudentProfileRules
{
    /**
     * @return array<string, mixed>
     */
    public static function fields(bool $requireStudentCode = false): array
    {
        $religion = implode(',', StudentReligion::values());

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'great_grandfather_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'maternal_father_name' => ['nullable', 'string', 'max:100'],
            'maternal_grandfather_name' => ['nullable', 'string', 'max:100'],
            'guardian_triple_name' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:100'],
            'locality' => ['nullable', 'string', 'max:100'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['required', 'date', 'before:today'],
            'mawalid_date' => ['nullable', 'date'],
            'registration_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'integer', 'in:1,2'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'integer', "in:{$religion}"],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'previous_school_name' => ['nullable', 'string', 'max:255'],
            'transfer_document_number' => ['nullable', 'integer', 'min:0'],
            'transfer_document_date' => ['nullable', 'date'],
            'school_start_date' => ['nullable', 'date'],
            'admitted_class_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'guardian_mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:100'],
            'specialization_name' => ['nullable', 'string', 'max:100'],
            'stage_name' => ['nullable', 'string', 'max:100'],
            'section_name' => ['nullable', 'string', 'max:100'],
            'student_code' => $requireStudentCode
                ? ['nullable', 'string', 'max:50']
                : ['prohibited'],
        ];
    }
}
