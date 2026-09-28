<?php

namespace App\Http\Controllers\Api;

use App\Application\Student\Commands\CreateStudentCommand;
use App\Application\Student\Commands\UpdateStudentCommand;
use App\Domain\Student\ValueObjects\StudentReligion;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;

/**
 * Maps validated student profile request fields to application commands.
 */
final class StudentProfileCommandFactory
{
    public static function createFromRequest(CreateStudentRequest $request, int $schoolId): CreateStudentCommand
    {
        $v = $request->validated();

        return new CreateStudentCommand(
            firstName: (string) $v['first_name'],
            lastName: (string) $v['last_name'],
            gender: (int) $v['gender'],
            birthDate: (string) $v['birth_date'],
            middleName: self::nullableString($v, 'middle_name'),
            fatherName: self::nullableString($v, 'father_name'),
            grandfatherName: self::nullableString($v, 'grandfather_name'),
            greatGrandfatherName: self::nullableString($v, 'great_grandfather_name'),
            motherName: self::nullableString($v, 'mother_name'),
            maternalFatherName: self::nullableString($v, 'maternal_father_name'),
            maternalGrandfatherName: self::nullableString($v, 'maternal_grandfather_name'),
            studentCode: self::nullableString($v, 'student_code'),
            nationalId: self::nullableString($v, 'national_id'),
            birthPlace: self::nullableString($v, 'birth_place'),
            nationality: self::nullableString($v, 'nationality'),
            guardianTripleName: self::nullableString($v, 'guardian_triple_name'),
            governorate: self::nullableString($v, 'governorate'),
            neighborhood: self::nullableString($v, 'neighborhood'),
            locality: self::nullableString($v, 'locality'),
            houseNumber: self::nullableString($v, 'house_number'),
            registrationPlace: self::nullableString($v, 'registration_place'),
            religion: isset($v['religion']) ? (int) $v['religion'] : StudentReligion::Muslim->value,
            mawalidDate: self::nullableString($v, 'mawalid_date'),
            previousSchoolName: self::nullableString($v, 'previous_school_name'),
            transferDocumentNumber: isset($v['transfer_document_number']) ? (int) $v['transfer_document_number'] : null,
            transferDocumentDate: self::nullableString($v, 'transfer_document_date'),
            schoolStartDate: self::nullableString($v, 'school_start_date'),
            admittedClassName: self::nullableString($v, 'admitted_class_name'),
            notes: self::nullableString($v, 'notes'),
            mobile: self::nullableString($v, 'mobile'),
            guardianMobile: self::nullableString($v, 'guardian_mobile'),
            email: self::nullableString($v, 'email'),
            schoolName: self::nullableString($v, 'school_name'),
            departmentName: self::nullableString($v, 'department_name'),
            fatherOccupation: self::nullableString($v, 'father_occupation'),
            motherOccupation: self::nullableString($v, 'mother_occupation'),
            administrativeUnit: isset($v['administrative_unit']) ? (int) $v['administrative_unit'] : null,
            graduationYear: isset($v['graduation_year']) ? (int) $v['graduation_year'] : null,
            previousGpa: isset($v['previous_gpa']) && $v['previous_gpa'] !== null && $v['previous_gpa'] !== '' ? (float) $v['previous_gpa'] : null,
            previousStudyTrack: isset($v['previous_study_track']) ? (int) $v['previous_study_track'] : null,
            mathematicsGrade: isset($v['mathematics_grade']) && $v['mathematics_grade'] !== null && $v['mathematics_grade'] !== '' ? (float) $v['mathematics_grade'] : null,
            physicsGrade: isset($v['physics_grade']) && $v['physics_grade'] !== null && $v['physics_grade'] !== '' ? (float) $v['physics_grade'] : null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
            schoolId: $schoolId,
        );
    }

    public static function updateFromRequest(UpdateStudentRequest $request, int $studentId): UpdateStudentCommand
    {
        $v = $request->validated();

        return new UpdateStudentCommand(
            studentId: $studentId,
            firstName: (string) $v['first_name'],
            lastName: (string) $v['last_name'],
            gender: (int) $v['gender'],
            birthDate: (string) $v['birth_date'],
            middleName: self::nullableString($v, 'middle_name'),
            fatherName: self::nullableString($v, 'father_name'),
            grandfatherName: self::nullableString($v, 'grandfather_name'),
            greatGrandfatherName: self::nullableString($v, 'great_grandfather_name'),
            motherName: self::nullableString($v, 'mother_name'),
            maternalFatherName: self::nullableString($v, 'maternal_father_name'),
            maternalGrandfatherName: self::nullableString($v, 'maternal_grandfather_name'),
            nationalId: self::nullableString($v, 'national_id'),
            birthPlace: self::nullableString($v, 'birth_place'),
            nationality: self::nullableString($v, 'nationality'),
            guardianTripleName: self::nullableString($v, 'guardian_triple_name'),
            governorate: self::nullableString($v, 'governorate'),
            neighborhood: self::nullableString($v, 'neighborhood'),
            locality: self::nullableString($v, 'locality'),
            houseNumber: self::nullableString($v, 'house_number'),
            registrationPlace: self::nullableString($v, 'registration_place'),
            religion: isset($v['religion']) ? (int) $v['religion'] : StudentReligion::Muslim->value,
            mawalidDate: self::nullableString($v, 'mawalid_date'),
            previousSchoolName: self::nullableString($v, 'previous_school_name'),
            transferDocumentNumber: isset($v['transfer_document_number']) ? (int) $v['transfer_document_number'] : null,
            transferDocumentDate: self::nullableString($v, 'transfer_document_date'),
            schoolStartDate: self::nullableString($v, 'school_start_date'),
            admittedClassName: self::nullableString($v, 'admitted_class_name'),
            notes: self::nullableString($v, 'notes'),
            mobile: self::nullableString($v, 'mobile'),
            guardianMobile: self::nullableString($v, 'guardian_mobile'),
            email: self::nullableString($v, 'email'),
            schoolName: self::nullableString($v, 'school_name'),
            departmentName: self::nullableString($v, 'department_name'),
            fatherOccupation: self::nullableString($v, 'father_occupation'),
            motherOccupation: self::nullableString($v, 'mother_occupation'),
            administrativeUnit: isset($v['administrative_unit']) ? (int) $v['administrative_unit'] : null,
            graduationYear: isset($v['graduation_year']) ? (int) $v['graduation_year'] : null,
            previousGpa: isset($v['previous_gpa']) && $v['previous_gpa'] !== null && $v['previous_gpa'] !== '' ? (float) $v['previous_gpa'] : null,
            previousStudyTrack: isset($v['previous_study_track']) ? (int) $v['previous_study_track'] : null,
            mathematicsGrade: isset($v['mathematics_grade']) && $v['mathematics_grade'] !== null && $v['mathematics_grade'] !== '' ? (float) $v['mathematics_grade'] : null,
            physicsGrade: isset($v['physics_grade']) && $v['physics_grade'] !== null && $v['physics_grade'] !== '' ? (float) $v['physics_grade'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private static function nullableString(array $v, string $key): ?string
    {
        if (! array_key_exists($key, $v) || $v[$key] === null || $v[$key] === '') {
            return null;
        }

        return (string) $v[$key];
    }
}
