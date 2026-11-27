<?php

namespace App\Infrastructure\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Contracts\CurriculumLineCatalogPort;
use App\Domain\Teachers\Data\CurriculumLinePlacement;
use Illuminate\Support\Facades\DB;

final class EloquentCurriculumLineCatalogAdapter implements CurriculumLineCatalogPort
{
    private const ACTIVE = 1;

    public function findLine(int $schoolId, int $lineId, bool $lock = false): ?CurriculumLinePlacement
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $query = DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.id', $lineId)
            ->where('c.school_id', $schoolId);
        if ($lock) {
            // Serialises teacher changes on one line (main teacher check + insert).
            $query->lockForUpdate();
        }
        $row = $query->first([
            'cs.id', 'cs.curriculum_id', 'cs.subject_id', 'cs.status as line_status',
            'c.school_id', 'c.academic_year_id', 'c.branch_id', 'c.department_id', 'c.class_id', 'c.section_id',
            'c.status as curriculum_status',
        ]);

        if ($row === null) {
            return null;
        }

        $nullable = static fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return new CurriculumLinePlacement(
            lineId: (int) $row->id,
            curriculumId: (int) $row->curriculum_id,
            subjectId: (int) $row->subject_id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            branchId: $nullable($row->branch_id),
            departmentId: $nullable($row->department_id),
            classId: $nullable($row->class_id),
            sectionId: $nullable($row->section_id),
            lineActive: (int) $row->line_status === self::ACTIVE,
            curriculumActive: (int) $row->curriculum_status === self::ACTIVE,
            subjectTypes: DB::table(SchemaHelper::qualified('curriculum', 'subject_types'))
                ->where('subject_id', (int) $row->subject_id)
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->map(static fn ($type): int => (int) $type)
                ->all(),
        );
    }
}
