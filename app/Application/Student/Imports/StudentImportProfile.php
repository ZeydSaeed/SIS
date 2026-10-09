<?php

namespace App\Application\Student\Imports;

use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Support\ImportContext;
use App\Application\Imports\Support\ImportValues;
use App\Application\Student\Commands\CreateStudentCommand;
use App\Application\Student\Commands\CreateStudentHandler;
use App\Domain\Student\Repositories\StudentRepositoryInterface;

/**
 * «استيراد الطلاب»: registers new students (the same CreateStudent path as the students page). A student already known
 * by رمز الطالب or الرقم الوطني is skipped — an import never overwrites a student record (personal data is edited on
 * the student's page). Placement / enrollment follows in «التسجيل» as usual.
 */
final class StudentImportProfile implements ImportProfile
{
    private const GENDERS = ['ذكر' => 1, 'male' => 1, 'm' => 1, 'انثي' => 2, 'female' => 2, 'f' => 2];

    private const RELIGIONS = ['مسلم' => 1, 'مسيحي' => 2, 'اخري' => 3, 'اخرى' => 3];

    public function __construct(
        private readonly StudentRepositoryInterface $students,
        private readonly CreateStudentHandler $create,
    ) {}

    public function kind(): string
    {
        return 'students';
    }

    public function ability(): string
    {
        return 'createStudents';
    }

    public function needsYear(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            ['key' => 'student_code', 'label' => 'رمز الطالب', 'required' => false, 'aliases' => ['student code', 'code'], 'example' => '', 'hint' => 'يُولّد تلقائياً إذا تُرك فارغاً'],
            ['key' => 'first_name', 'label' => 'الاسم', 'required' => true, 'aliases' => ['first name'], 'example' => 'أحمد'],
            ['key' => 'father_name', 'label' => 'اسم الأب', 'required' => false, 'aliases' => ['father name'], 'example' => 'علي'],
            ['key' => 'grandfather_name', 'label' => 'اسم الجد', 'required' => false, 'aliases' => ['grandfather name'], 'example' => 'حسن'],
            ['key' => 'great_grandfather_name', 'label' => 'اسم أب الجد', 'required' => false, 'aliases' => ['great grandfather name'], 'example' => ''],
            ['key' => 'last_name', 'label' => 'اللقب', 'required' => true, 'aliases' => ['last name'], 'example' => 'الموصلي'],
            ['key' => 'mother_name', 'label' => 'اسم الأم', 'required' => false, 'aliases' => ['mother name'], 'example' => ''],
            ['key' => 'gender', 'label' => 'الجنس', 'required' => true, 'aliases' => ['gender'], 'example' => 'ذكر'],
            ['key' => 'birth_date', 'label' => 'تاريخ الميلاد', 'required' => true, 'aliases' => ['birth date', 'date of birth'], 'example' => '2010-05-14'],
            ['key' => 'birth_place', 'label' => 'محل الولادة', 'required' => false, 'aliases' => ['birth place'], 'example' => 'نينوى'],
            ['key' => 'national_id', 'label' => 'الرقم الوطني', 'required' => false, 'aliases' => ['national id'], 'example' => ''],
            ['key' => 'nationality', 'label' => 'الجنسية', 'required' => false, 'aliases' => ['nationality'], 'example' => 'عراقية'],
            ['key' => 'religion', 'label' => 'الديانة', 'required' => false, 'aliases' => ['religion'], 'example' => 'مسلم'],
            ['key' => 'governorate', 'label' => 'المحافظة', 'required' => false, 'aliases' => ['governorate'], 'example' => 'نينوى'],
            ['key' => 'mobile', 'label' => 'هاتف الطالب', 'required' => false, 'aliases' => ['mobile', 'phone'], 'example' => ''],
            ['key' => 'guardian_mobile', 'label' => 'هاتف ولي الأمر', 'required' => false, 'aliases' => ['guardian mobile'], 'example' => ''],
            ['key' => 'department_name', 'label' => 'الاختصاص', 'required' => false, 'aliases' => ['department', 'specialization'], 'example' => 'الكهرباء'],
            ['key' => 'admitted_class_name', 'label' => 'الصف', 'required' => false, 'aliases' => ['class', 'grade'], 'example' => 'الأول'],
            ['key' => 'notes', 'label' => 'الملاحظات', 'required' => false, 'aliases' => ['notes'], 'example' => ''],
        ];
    }

    public function plan(ImportContext $context, array $row): array
    {
        $errors = [];
        foreach (['first_name', 'last_name', 'gender', 'birth_date'] as $required) {
            if (($row[$required] ?? '') === '') {
                $errors[] = 'import.required:'.$required;
            }
        }
        $gender = ($row['gender'] ?? '') === '' ? null : (ImportValues::int($row['gender'], 1, 2) ?? self::GENDERS[ImportValues::header($row['gender'])] ?? false);
        if ($gender === false) {
            $errors[] = 'import.gender_invalid';
        }
        $birthDate = ImportValues::date($row['birth_date'] ?? '');
        if (($row['birth_date'] ?? '') !== '' && ($birthDate === null || $birthDate >= date('Y-m-d') || $birthDate <= '1950-01-01')) {
            $errors[] = 'import.date_invalid:birth_date';
        }
        $religion = ($row['religion'] ?? '') === '' ? 1 : (ImportValues::int($row['religion'], 1, 3) ?? self::RELIGIONS[ImportValues::header($row['religion'])] ?? false);
        if ($religion === false) {
            $errors[] = 'import.religion_invalid';
        }
        foreach (['first_name' => 100, 'last_name' => 100, 'national_id' => 20, 'student_code' => 50] as $key => $max) {
            if (mb_strlen($row[$key] ?? '') > $max) {
                $errors[] = 'import.too_long:'.$key;
            }
        }

        $code = ImportValues::nullable($row['student_code'] ?? '');
        $nationalId = ImportValues::nullable($row['national_id'] ?? '');
        $exists = ($code !== null && $this->students->existsByCode($code)) || ($nationalId !== null && $this->students->existsByNationalId($nationalId));
        $nameKey = ImportValues::header(($row['first_name'] ?? '').($row['father_name'] ?? '').($row['grandfather_name'] ?? '').($row['last_name'] ?? '')).'|'.($birthDate ?? '');

        return [
            'action' => $exists ? self::SKIP : self::CREATE,
            'errors' => $errors,
            'key' => $nationalId !== null ? 'nid:'.$nationalId : ($code !== null ? 'code:'.$code : 'name:'.$nameKey),
            'entity_id' => null,
            'data' => [
                'student_code' => $code,
                'first_name' => (string) ImportValues::nullable($row['first_name'] ?? ''),
                'father_name' => ImportValues::nullable($row['father_name'] ?? ''),
                'grandfather_name' => ImportValues::nullable($row['grandfather_name'] ?? ''),
                'great_grandfather_name' => ImportValues::nullable($row['great_grandfather_name'] ?? ''),
                'last_name' => (string) ImportValues::nullable($row['last_name'] ?? ''),
                'mother_name' => ImportValues::nullable($row['mother_name'] ?? ''),
                'gender' => is_int($gender) ? $gender : 1,
                'birth_date' => $birthDate,
                'birth_place' => ImportValues::nullable($row['birth_place'] ?? ''),
                'national_id' => $nationalId,
                'nationality' => ImportValues::nullable($row['nationality'] ?? ''),
                'religion' => is_int($religion) ? $religion : 1,
                'governorate' => ImportValues::nullable($row['governorate'] ?? ''),
                'mobile' => ImportValues::nullable($row['mobile'] ?? ''),
                'guardian_mobile' => ImportValues::nullable($row['guardian_mobile'] ?? ''),
                'department_name' => ImportValues::nullable($row['department_name'] ?? ''),
                'admitted_class_name' => ImportValues::nullable($row['admitted_class_name'] ?? ''),
                'notes' => ImportValues::nullable($row['notes'] ?? ''),
                'exists' => $exists,
            ],
        ];
    }

    public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array
    {
        $result = $this->create->handle(new CreateStudentCommand(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            gender: $data['gender'],
            birthDate: (string) $data['birth_date'],
            fatherName: $data['father_name'],
            grandfatherName: $data['grandfather_name'],
            greatGrandfatherName: $data['great_grandfather_name'],
            motherName: $data['mother_name'],
            studentCode: $data['student_code'],
            nationalId: $data['national_id'],
            birthPlace: $data['birth_place'],
            nationality: $data['nationality'],
            governorate: $data['governorate'],
            religion: $data['religion'],
            admittedClassName: $data['admitted_class_name'],
            notes: $data['notes'],
            mobile: $data['mobile'],
            guardianMobile: $data['guardian_mobile'],
            departmentName: $data['department_name'],
            idempotencyKey: $idempotencyKey,
            schoolId: $context->schoolId,
        ));

        return $result->failed() || $result->studentId === null
            ? ['ok' => false, 'entity_id' => null, 'error' => $result->errors[0] ?? 'import.row_failed']
            : ['ok' => true, 'entity_id' => $result->studentId, 'error' => null];
    }
}
