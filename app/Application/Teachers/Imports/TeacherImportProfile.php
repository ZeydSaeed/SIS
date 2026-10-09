<?php

namespace App\Application\Teachers\Imports;

use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Support\ImportContext;
use App\Application\Imports\Support\ImportValues;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\ChangeTeachersStatusCommand;
use App\Application\Teachers\Commands\ChangeTeachersStatusHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Application\Teachers\Commands\UpdateTeacherCommand;
use App\Application\Teachers\Commands\UpdateTeacherHandler;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Teachers\Repositories\AcademicTitleRepositoryInterface;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

/**
 * «استيراد المعلمين»: a row per teacher keyed by الرقم الوظيفي. A new code registers the teacher in the school-year;
 * a code already in the school updates the profile (blank cells keep the stored values); a code of another school is
 * refused. «المواد» (codes or names) become المواد المسندة. Every write goes through the Teachers handlers.
 */
final class TeacherImportProfile implements ImportProfile
{
    private const EMPLOYMENT = ['ملاك' => 1, 'مكلف' => 2, 'تنسيب' => 3, 'محاضر' => 4, 'عقد' => 5];

    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
        private readonly AcademicTitleRepositoryInterface $titles,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly RegisterTeacherHandler $register,
        private readonly UpdateTeacherHandler $update,
        private readonly ChangeTeachersStatusHandler $status,
        private readonly AssignTeacherSubjectHandler $assign,
    ) {}

    public function kind(): string
    {
        return 'teachers';
    }

    public function ability(): string
    {
        return 'manageTeachers';
    }

    public function needsYear(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ['key' => 'employee_code', 'label' => 'الرقم الوظيفي', 'required' => true, 'aliases' => ['employee code', 'code', 'الرمز'], 'example' => 'T-1001'],
            ['key' => 'first_name', 'label' => 'الاسم', 'required' => true, 'aliases' => ['first name', 'الاسم الاول'], 'example' => 'نور الهدى'],
            ['key' => 'father_name', 'label' => 'اسم الأب', 'required' => false, 'aliases' => ['father name', 'الاب'], 'example' => 'علي'],
            ['key' => 'grandfather_name', 'label' => 'اسم الجد', 'required' => false, 'aliases' => ['grandfather name', 'الجد'], 'example' => 'حسن'],
            ['key' => 'last_name', 'label' => 'اللقب', 'required' => true, 'aliases' => ['last name', 'family name'], 'example' => 'الموصلي'],
            ['key' => 'abbreviation', 'label' => 'الاختصار', 'required' => false, 'aliases' => ['abbreviation', 'short name', 'اختصار'], 'example' => 'نور الهدى'],
            ['key' => 'academic_title', 'label' => 'اللقب العلمي', 'required' => false, 'aliases' => ['title', 'academic title'], 'example' => 'مدرس'],
            ['key' => 'specialization_field', 'label' => 'التخصص', 'required' => false, 'aliases' => ['specialization', 'تخصص الشهادة'], 'example' => 'هندسة حاسوب'],
            ['key' => 'national_id', 'label' => 'الرقم الوطني', 'required' => false, 'aliases' => ['national id'], 'example' => ''],
            ['key' => 'hire_date', 'label' => 'تاريخ التعيين', 'required' => false, 'aliases' => ['hire date'], 'example' => '2015-09-01'],
            ['key' => 'employment_type', 'label' => 'نوع التعيين', 'required' => false, 'aliases' => ['employment type'], 'example' => 'ملاك'],
            ['key' => 'status', 'label' => 'الحالة', 'required' => false, 'aliases' => ['status'], 'example' => 'نشط'],
            ['key' => 'subjects', 'label' => 'المواد', 'required' => false, 'aliases' => ['subjects', 'المواد المسندة'], 'example' => 'حاسوب، مبادئ حاسوب', 'hint' => 'رموز أو أسماء مفصولة بفاصلة'],
        ];
    }

    public function plan(ImportContext $context, array $row): array
    {
        $errors = [];
        foreach (['employee_code', 'first_name', 'last_name'] as $required) {
            if (($row[$required] ?? '') === '') {
                $errors[] = 'import.required:'.$required;
            }
        }
        $hireDate = ImportValues::date($row['hire_date'] ?? '');
        if (($row['hire_date'] ?? '') !== '' && $hireDate === null) {
            $errors[] = 'import.date_invalid:hire_date';
        }
        $status = ImportValues::status($row['status'] ?? '');
        if ($status === null) {
            $errors[] = 'import.status_invalid';
        }
        $employment = self::employment($row['employment_type'] ?? '');
        if ($employment === false) {
            $errors[] = 'import.employment_type_invalid';
        }
        $titleId = $this->titleId($context, $row['academic_title'] ?? '');
        if ($titleId === false) {
            $errors[] = 'import.academic_title_unknown';
        }
        [$subjectIds, $unknown] = $this->subjectIds($context, ImportValues::list($row['subjects'] ?? ''));
        foreach ($unknown as $name) {
            $errors[] = 'import.subject_unknown:'.$name;
        }
        if (mb_strlen($row['abbreviation'] ?? '') > 20) {
            $errors[] = 'appearance.abbreviation_too_long';
        }

        $code = strtoupper(trim($row['employee_code'] ?? ''));
        $existing = $code === '' ? null : $this->teachers->importIdentity($code, $context->schoolId, (int) $context->academicYearId);
        if ($existing !== null && ! $existing['in_school']) {
            $errors[] = 'import.teacher_other_school';
        }

        return [
            'action' => $existing === null ? self::CREATE : self::UPDATE,
            'errors' => $errors,
            'key' => $code === '' ? null : 'code:'.$code,
            'entity_id' => $existing['id'] ?? null,
            'data' => [
                'employee_code' => $code,
                'first_name' => ImportValues::nullable($row['first_name'] ?? '') ?? $existing['first_name'] ?? '',
                'father_name' => ImportValues::nullable($row['father_name'] ?? '') ?? $existing['father_name'] ?? null,
                'grandfather_name' => ImportValues::nullable($row['grandfather_name'] ?? '') ?? $existing['grandfather_name'] ?? null,
                'last_name' => ImportValues::nullable($row['last_name'] ?? '') ?? $existing['last_name'] ?? '',
                'abbreviation' => ImportValues::nullable($row['abbreviation'] ?? '') ?? $existing['abbreviation'] ?? null,
                'academic_title_id' => is_int($titleId) ? $titleId : ($existing['academic_title_id'] ?? null),
                'specialization_field' => ImportValues::nullable($row['specialization_field'] ?? '') ?? $existing['specialization_field'] ?? null,
                'national_id' => ImportValues::nullable($row['national_id'] ?? '') ?? $existing['national_id'] ?? null,
                'hire_date' => $hireDate ?? $existing['hire_date'] ?? null,
                'employment_type' => is_int($employment) ? $employment : null,
                'status' => $status ?? 1,
                'subject_ids' => $subjectIds,
            ],
        ];
    }

    public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array
    {
        $year = (int) $context->academicYearId;
        if ($action === self::CREATE) {
            $result = $this->register->handle(new RegisterTeacherCommand(
                schoolId: $context->schoolId, academicYearId: $year, employeeCode: $data['employee_code'], firstName: $data['first_name'], lastName: $data['last_name'],
                nationalId: $data['national_id'], specializationField: $data['specialization_field'], hireDate: $data['hire_date'], userId: null,
                idempotencyKey: $idempotencyKey, fatherName: $data['father_name'], grandfatherName: $data['grandfather_name'],
                employmentType: $data['employment_type'], academicTitleId: $data['academic_title_id'], abbreviation: $data['abbreviation'],
            ));
            $teacherId = $result->teacherId;
        } else {
            $result = $this->update->handle(new UpdateTeacherCommand(
                schoolId: $context->schoolId, teacherId: (int) $entityId, firstName: $data['first_name'], lastName: $data['last_name'],
                nationalId: $data['national_id'], specializationField: $data['specialization_field'], hireDate: $data['hire_date'], userId: null,
                idempotencyKey: $idempotencyKey, fatherName: $data['father_name'], grandfatherName: $data['grandfather_name'],
                academicYearId: $data['employment_type'] === null ? null : $year, employmentType: $data['employment_type'],
                updateTitle: true, academicTitleId: $data['academic_title_id'], abbreviation: $data['abbreviation'],
            ));
            $teacherId = $entityId;
        }
        if ($result->failed() || $teacherId === null) {
            return ['ok' => false, 'entity_id' => null, 'error' => $result->errors[0] ?? 'import.row_failed'];
        }
        if ($data['status'] === 2) {
            $this->status->handle(new ChangeTeachersStatusCommand($context->schoolId, [$teacherId], 2, $idempotencyKey.'-status'));
        }
        foreach ($data['subject_ids'] as $subjectId) {
            $this->assign->handle(new AssignTeacherSubjectCommand($context->schoolId, $teacherId, (int) $subjectId, $year, $idempotencyKey.'-s'.$subjectId));
        }

        return ['ok' => true, 'entity_id' => $teacherId, 'error' => null];
    }

    private static function employment(string $value): int|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $number = ImportValues::int($value, 1, 5);

        return $number ?? self::EMPLOYMENT[$value] ?? false;
    }

    private function titleId(ImportContext $context, string $value): int|false|null
    {
        if (trim($value) === '') {
            return null;
        }
        $titles = $context->remember('teacher_titles', fn (): array => $this->titles->active());
        $needle = ImportValues::header($value);
        foreach ($titles as $title) {
            if (in_array($needle, array_map(static fn (?string $v): string => ImportValues::header((string) $v), [$title['name'], $title['code'], $title['abbreviation']]), true)) {
                return $title['id'];
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     * @return array{0: list<int>, 1: list<string>}
     */
    private function subjectIds(ImportContext $context, array $names): array
    {
        if ($names === []) {
            return [[], []];
        }
        $index = $context->remember('subject_index', function (): array {
            $map = [];
            foreach ($this->subjects->listActive() as $subject) {
                $map[ImportValues::header($subject->code)] = $subject->id;
                $map[ImportValues::header($subject->name)] = $subject->id;
            }

            return $map;
        });
        $ids = [];
        $unknown = [];
        foreach ($names as $name) {
            $id = $index[ImportValues::header($name)] ?? null;
            $id === null ? $unknown[] = $name : $ids[] = $id;
        }

        return [array_values(array_unique($ids)), $unknown];
    }
}
