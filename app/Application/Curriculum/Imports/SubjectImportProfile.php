<?php

namespace App\Application\Curriculum\Imports;

use App\Application\Curriculum\Commands\CreateSubjectCommand;
use App\Application\Curriculum\Commands\CreateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectHandler;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\UpdateSubjectCommand;
use App\Application\Curriculum\Commands\UpdateSubjectHandler;
use App\Application\Imports\Contracts\ImportProfile;
use App\Application\Imports\Support\ImportContext;
use App\Application\Imports\Support\ImportValues;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

/**
 * «استيراد المواد والمناهج»: a row per subject keyed by رمز المادة (the subject catalogue). A new code creates the
 * subject; an existing one is updated with the cells given (blank = unchanged). «المنهج» (an active curriculum of the
 * school-year, by name) + «الحصص الأسبوعية» link the subject to that curriculum. Writes go through Curriculum handlers.
 */
final class SubjectImportProfile implements ImportProfile
{
    private const TYPES = ['اساسي' => 1, 'اساسيه' => 1, 'نظري' => 1, 'core' => 1, 'اختياري' => 2, 'elective' => 2, 'عملي' => 3, 'practical' => 3];

    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly CreateSubjectHandler $create,
        private readonly UpdateSubjectHandler $update,
        private readonly DeactivateSubjectHandler $deactivate,
        private readonly LinkCurriculumSubjectHandler $link,
    ) {}

    public function kind(): string
    {
        return 'subjects';
    }

    public function ability(): string
    {
        return 'manageCurriculum';
    }

    public function needsYear(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'رمز المادة', 'required' => true, 'aliases' => ['code', 'subject code', 'الرمز'], 'example' => 'CS-101'],
            ['key' => 'name', 'label' => 'اسم المادة', 'required' => true, 'aliases' => ['name', 'subject', 'المادة'], 'example' => 'مبادئ حاسوب'],
            ['key' => 'name_en', 'label' => 'الاسم الإنجليزي', 'required' => false, 'aliases' => ['english name', 'name en'], 'example' => 'Computer Basics'],
            ['key' => 'abbreviation', 'label' => 'الاختصار', 'required' => false, 'aliases' => ['abbreviation', 'short name'], 'example' => 'م.ح'],
            ['key' => 'subject_type', 'label' => 'نوع المادة', 'required' => false, 'aliases' => ['type', 'subject type', 'النوع'], 'example' => 'نظري', 'hint' => 'نظري / اختياري / عملي'],
            ['key' => 'credit_hours', 'label' => 'الساعات', 'required' => false, 'aliases' => ['credit hours', 'hours'], 'example' => '3'],
            ['key' => 'max_grade', 'label' => 'الدرجة العظمى', 'required' => false, 'aliases' => ['max grade'], 'example' => '100'],
            ['key' => 'pass_grade', 'label' => 'درجة النجاح', 'required' => false, 'aliases' => ['pass grade'], 'example' => '50'],
            ['key' => 'status', 'label' => 'الحالة', 'required' => false, 'aliases' => ['status'], 'example' => 'نشط'],
            ['key' => 'curriculum', 'label' => 'المنهج', 'required' => false, 'aliases' => ['curriculum', 'الخطة'], 'example' => '', 'hint' => 'اسم منهج فعّال في السنة الدراسية'],
            ['key' => 'weekly_hours', 'label' => 'الحصص الأسبوعية', 'required' => false, 'aliases' => ['weekly hours', 'النصاب'], 'example' => '4'],
        ];
    }

    public function plan(ImportContext $context, array $row): array
    {
        $errors = [];
        foreach (['code', 'name'] as $required) {
            if (($row[$required] ?? '') === '') {
                $errors[] = 'import.required:'.$required;
            }
        }
        $type = self::type($row['subject_type'] ?? '');
        if ($type === false) {
            $errors[] = 'import.subject_type_invalid';
        }
        $numbers = [];
        foreach (['credit_hours' => [0, 40], 'max_grade' => [1, 1000], 'pass_grade' => [0, 1000], 'weekly_hours' => [1, 40]] as $key => [$min, $max]) {
            $numbers[$key] = ($row[$key] ?? '') === '' ? null : ImportValues::int($row[$key], $min, $max);
            if (($row[$key] ?? '') !== '' && $numbers[$key] === null) {
                $errors[] = 'import.number_invalid:'.$key;
            }
        }
        $status = ImportValues::status($row['status'] ?? '');
        if ($status === null) {
            $errors[] = 'import.status_invalid';
        }
        if (mb_strlen($row['abbreviation'] ?? '') > 20) {
            $errors[] = 'appearance.abbreviation_too_long';
        }
        $curriculumId = null;
        if (($row['curriculum'] ?? '') !== '') {
            $curriculumId = $this->curriculumId($context, $row['curriculum']);
            if ($curriculumId === null) {
                $errors[] = 'import.curriculum_unknown';
            }
        }

        $code = strtoupper(trim($row['code'] ?? ''));
        $existing = $code === '' ? null : ($this->subjectIndex($context)[$code] ?? null);

        return [
            'action' => $existing === null ? self::CREATE : self::UPDATE,
            'errors' => $errors,
            'key' => $code === '' ? null : 'code:'.$code,
            'entity_id' => $existing,
            'data' => [
                'code' => $code,
                'name' => ImportValues::nullable($row['name'] ?? ''),
                'name_en' => ImportValues::nullable($row['name_en'] ?? ''),
                'abbreviation' => ImportValues::nullable($row['abbreviation'] ?? ''),
                'subject_type' => is_int($type) ? $type : null,
                'credit_hours' => $numbers['credit_hours'],
                'max_grade' => $numbers['max_grade'],
                'pass_grade' => $numbers['pass_grade'],
                'status' => $status ?? 1,
                'curriculum_id' => $curriculumId,
                'weekly_hours' => $numbers['weekly_hours'],
            ],
        ];
    }

    public function commit(ImportContext $context, array $data, int $action, ?int $entityId, string $idempotencyKey): array
    {
        if ($action === self::CREATE) {
            $result = $this->create->handle(new CreateSubjectCommand(
                $data['code'], (string) $data['name'], $data['name_en'], $data['subject_type'] ?? 1, $data['credit_hours'],
                $data['max_grade'] ?? 100, $data['pass_grade'] ?? 50, $idempotencyKey,
            ));
            $subjectId = $result->subjectId;
            $fields = $data['abbreviation'] === null ? [] : ['abbreviation' => $data['abbreviation']];
        } else {
            $subjectId = $entityId;
            $fields = array_filter([
                'name' => $data['name'], 'name_en' => $data['name_en'], 'subject_type' => $data['subject_type'], 'credit_hours' => $data['credit_hours'],
                'max_grade' => $data['max_grade'], 'pass_grade' => $data['pass_grade'], 'abbreviation' => $data['abbreviation'],
            ], static fn ($v): bool => $v !== null);
            $result = null;
        }
        if (($result !== null && $result->failed()) || $subjectId === null) {
            return ['ok' => false, 'entity_id' => null, 'error' => $result?->errors[0] ?? 'import.row_failed'];
        }
        if ($fields !== []) {
            $updated = $this->update->handle(new UpdateSubjectCommand($subjectId, $fields, $idempotencyKey.'-u'));
            if ($updated->failed()) {
                return ['ok' => false, 'entity_id' => $subjectId, 'error' => $updated->errors[0] ?? 'import.row_failed'];
            }
        }
        $alreadyLinked = $data['curriculum_id'] !== null && array_filter(
            $this->curricula->listActiveLinks($context->schoolId, (int) $data['curriculum_id']),
            static fn ($link): bool => $link->subjectId === $subjectId,
        ) !== [];
        if ($data['curriculum_id'] !== null && ! $alreadyLinked) {
            $linked = $this->link->handle(new LinkCurriculumSubjectCommand($context->schoolId, (int) $data['curriculum_id'], $subjectId, $data['weekly_hours'], true, 0, $idempotencyKey.'-l'));
            if ($linked->failed()) {
                return ['ok' => false, 'entity_id' => $subjectId, 'error' => $linked->errors[0] ?? 'import.row_failed'];
            }
        }
        if ($data['status'] === 2) {
            $this->deactivate->handle(new DeactivateSubjectCommand($subjectId, $idempotencyKey.'-off'));
        }
        $context->forget('subject_index');

        return ['ok' => true, 'entity_id' => $subjectId, 'error' => null];
    }

    private static function type(string $value): int|false|null
    {
        if (trim($value) === '') {
            return null;
        }

        return ImportValues::int($value, 1, 3) ?? self::TYPES[ImportValues::header($value)] ?? false;
    }

    /** @return array<string, int> upper(code) → subject id (the whole catalogue, active or not) */
    private function subjectIndex(ImportContext $context): array
    {
        return $context->remember('subject_index', function (): array {
            $index = [];
            foreach ($this->subjects->listAll() as $subject) {
                $index[strtoupper($subject->code)] = $subject->id;
            }

            return $index;
        });
    }

    private function curriculumId(ImportContext $context, string $name): ?int
    {
        $needle = ImportValues::header($name);
        foreach ($context->remember('curricula', fn (): array => $this->curricula->listActiveForSchool($context->schoolId, (int) $context->academicYearId)) as $curriculum) {
            if (ImportValues::header($curriculum->name) === $needle || (string) $curriculum->id === trim($name)) {
                return $curriculum->id;
            }
        }

        return null;
    }
}
