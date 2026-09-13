<?php

namespace App\Infrastructure\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Contracts\PrerequisitePassEvidencePort;
use App\Domain\Exams\ValueObjects\GradeStatus;
use Illuminate\Support\Facades\DB;

/**
 * CUR-U06 — finalized current grade meeting subject catalog pass_grade.
 */
final class EloquentPrerequisitePassEvidenceAdapter implements PrerequisitePassEvidencePort
{
    public function studentHasPassingGrade(int $schoolId, int $studentId, int $subjectId): bool
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return DB::table(SchemaHelper::qualified('exams', 'student_grades').' as g')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as s', 's.id', '=', 'g.subject_id')
            ->where('g.school_id', $schoolId)
            ->where('g.student_id', $studentId)
            ->where('g.subject_id', $subjectId)
            ->where('g.is_current', true)
            ->where('g.status', GradeStatus::Finalized->value)
            ->where('g.is_absent', false)
            ->whereNotNull('g.score')
            ->whereColumn('g.score', '>=', 's.pass_grade')
            ->exists();
    }
}
