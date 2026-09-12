<?php

namespace App\Infrastructure\Persistence\Results;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Domain\Results\Data\CurrentTermResultSnapshot;
use App\Domain\Results\Data\PersistOperationalTermResultData;
use App\Domain\Results\Data\TermGradeContribution;
use App\Domain\Results\Repositories\TermResultRepositoryInterface;
use App\Domain\Results\ValueObjects\TermResultLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTermResultRepository implements TermResultRepositoryInterface
{
    public function listOperationalContributions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array {
        $grades = SchemaHelper::qualified('exams', 'student_grades');
        $enrollments = SchemaHelper::qualified('exams', 'exam_enrollments');
        $sessions = SchemaHelper::qualified('exams', 'exam_sessions');
        $exams = SchemaHelper::qualified('exams', 'exams');
        $types = SchemaHelper::qualified('exams', 'exam_types');

        $rows = DB::table("{$grades} as g")
            ->join("{$enrollments} as ee", function ($join) {
                $join->on('ee.id', '=', 'g.exam_enrollment_id')
                    ->on('ee.school_id', '=', 'g.school_id');
            })
            ->join("{$sessions} as es", function ($join) {
                $join->on('es.id', '=', 'ee.exam_session_id')
                    ->on('es.school_id', '=', 'g.school_id');
            })
            ->join("{$exams} as ex", function ($join) {
                $join->on('ex.id', '=', 'es.exam_id')
                    ->on('ex.school_id', '=', 'g.school_id');
            })
            ->join("{$types} as et", 'et.id', '=', 'ex.exam_type_id')
            ->where('g.school_id', $schoolId)
            ->where('g.enrollment_id', $enrollmentId)
            ->where('g.academic_year_id', $academicYearId)
            ->where('g.subject_id', $subjectId)
            ->where('ex.term_id', $termId)
            ->where('g.is_current', true)
            ->whereIn('g.status', [GradeStatus::Entered->value, GradeStatus::Finalized->value])
            ->select([
                'g.id as grade_id',
                'et.id as exam_type_id',
                'et.weight_percentage',
                'g.score',
                'g.max_score',
                'g.is_absent',
                'g.status',
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = new TermGradeContribution(
                gradeId: (int) $row->grade_id,
                examTypeId: (int) $row->exam_type_id,
                weightPercentage: (int) $row->weight_percentage,
                score: $row->score !== null ? (string) $row->score : null,
                maxScore: (string) $row->max_score,
                isAbsent: (bool) $row->is_absent,
                status: (int) $row->status,
            );
        }

        return $out;
    }

    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId, int $academicYearId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $enrollmentId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->first(['student_id', 'academic_year_id']);

        if ($row === null) {
            return null;
        }

        return [
            'student_id' => (int) $row->student_id,
            'academic_year_id' => (int) $row->academic_year_id,
        ];
    }

    public function nextResultVersion(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): int {
        $max = DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('term_id', $termId)
            ->where('subject_id', $subjectId)
            ->max('result_version');

        return ((int) $max) + 1;
    }

    public function listOfficialContributions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array {
        return array_values(array_filter(
            $this->listOperationalContributions($schoolId, $enrollmentId, $academicYearId, $termId, $subjectId),
            static fn (TermGradeContribution $c): bool => $c->status === GradeStatus::Finalized->value,
        ));
    }

    public function listRequiredSessionIds(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array {
        $enrollments = SchemaHelper::qualified('exams', 'exam_enrollments');
        $sessions = SchemaHelper::qualified('exams', 'exam_sessions');
        $exams = SchemaHelper::qualified('exams', 'exams');

        return DB::table("{$enrollments} as ee")
            ->join("{$sessions} as es", function ($join) {
                $join->on('es.id', '=', 'ee.exam_session_id')
                    ->on('es.school_id', '=', 'ee.school_id');
            })
            ->join("{$exams} as ex", function ($join) {
                $join->on('ex.id', '=', 'es.exam_id')
                    ->on('ex.school_id', '=', 'ee.school_id');
            })
            ->where('ee.school_id', $schoolId)
            ->where('ee.enrollment_id', $enrollmentId)
            ->where('ex.academic_year_id', $academicYearId)
            ->where('ex.term_id', $termId)
            ->where('es.subject_id', $subjectId)
            ->pluck('es.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function countFinalizedCurrentGradesForSessions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        array $sessionIds,
    ): int {
        if ($sessionIds === []) {
            return 0;
        }

        return (int) DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->whereIn('exam_session_id', $sessionIds)
            ->where('is_current', true)
            ->where('status', GradeStatus::Finalized->value)
            ->count();
    }

    public function insertOperational(PersistOperationalTermResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'term_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('term_id', $data->termId)
            ->where('subject_id', $data->subjectId)
            ->where('is_current_operational', true)
            ->update([
                'is_current_operational' => false,
                'lifecycle_status' => TermResultLifecycleStatus::Superseded->value,
                'superseded_at' => $data->calculatedAt,
                'updated_at' => $data->calculatedAt,
            ]);

        return (int) DB::table($table)->insertGetId([
            'school_id' => $data->schoolId,
            'enrollment_id' => $data->enrollmentId,
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'subject_id' => $data->subjectId,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => TermResultLifecycleStatus::Calculated->value,
            'is_official' => false,
            'is_current_operational' => true,
            'is_current_official' => false,
            'weighted_total' => $data->weightedTotal,
            'pass_fail' => $data->passFail,
            'incomplete' => $data->incomplete,
            'source_fingerprint' => $data->sourceFingerprint,
            'calculation_version' => $data->calculationVersion,
            'policy_pin' => json_encode($data->policyPin, JSON_THROW_ON_ERROR),
            'calculated_at' => $data->calculatedAt,
            'finalized_at' => null,
            'superseded_at' => null,
            'correlation_id' => $data->correlationId,
            'created_by' => $data->createdBy,
            'created_at' => $data->calculatedAt,
            'updated_at' => $data->calculatedAt,
        ]);
    }

    public function insertOfficial(PersistOperationalTermResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'term_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('term_id', $data->termId)
            ->where('subject_id', $data->subjectId)
            ->where('is_current_official', true)
            ->update([
                'is_current_official' => false,
                'lifecycle_status' => TermResultLifecycleStatus::Superseded->value,
                'superseded_at' => $data->calculatedAt,
                'updated_at' => $data->calculatedAt,
            ]);

        return (int) DB::table($table)->insertGetId([
            'school_id' => $data->schoolId,
            'enrollment_id' => $data->enrollmentId,
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'subject_id' => $data->subjectId,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => TermResultLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'weighted_total' => $data->weightedTotal,
            'pass_fail' => $data->passFail,
            'incomplete' => $data->incomplete,
            'source_fingerprint' => $data->sourceFingerprint,
            'calculation_version' => $data->calculationVersion,
            'policy_pin' => json_encode($data->policyPin, JSON_THROW_ON_ERROR),
            'calculated_at' => $data->calculatedAt,
            'finalized_at' => $data->calculatedAt,
            'superseded_at' => null,
            'correlation_id' => $data->correlationId,
            'created_by' => $data->createdBy,
            'created_at' => $data->calculatedAt,
            'updated_at' => $data->calculatedAt,
        ]);
    }

    public function findCurrentOperational(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): ?CurrentTermResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $termId, $subjectId, 'is_current_operational');
    }

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): ?CurrentTermResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $termId, $subjectId, 'is_current_official');
    }

    private function findCurrent(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
        string $flag,
    ): ?CurrentTermResultSnapshot {
        $row = DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('term_id', $termId)
            ->where('subject_id', $subjectId)
            ->where($flag, true)
            ->first([
                'id',
                'result_version',
                'source_fingerprint',
                'weighted_total',
                'incomplete',
            ]);

        if ($row === null) {
            return null;
        }

        return new CurrentTermResultSnapshot(
            id: (int) $row->id,
            resultVersion: (int) $row->result_version,
            sourceFingerprint: (string) $row->source_fingerprint,
            weightedTotal: $row->weighted_total !== null ? (string) $row->weighted_total : null,
            incomplete: (bool) $row->incomplete,
        );
    }
}
