<?php

namespace App\Infrastructure\Persistence\Results;

use App\Database\SchemaHelper;
use App\Domain\Results\Data\CurrentAnnualResultSnapshot;
use App\Domain\Results\Data\PersistOperationalAnnualResultData;
use App\Domain\Results\Data\TermResultRollupRow;
use App\Domain\Results\Repositories\AnnualResultRepositoryInterface;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAnnualResultRepository implements AnnualResultRepositoryInterface
{
    public function listCurrentOperationalTermRows(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): array {
        return $this->listCurrentTermRows($schoolId, $enrollmentId, $academicYearId, 'is_current_operational');
    }

    public function listCurrentOfficialTermRows(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): array {
        return $this->listCurrentTermRows($schoolId, $enrollmentId, $academicYearId, 'is_current_official');
    }

    /**
     * @return list<TermResultRollupRow>
     */
    private function listCurrentTermRows(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        string $flag,
    ): array {
        $rows = DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where($flag, true)
            ->get([
                'id',
                'term_id',
                'subject_id',
                'weighted_total',
                'pass_fail',
                'incomplete',
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = new TermResultRollupRow(
                termResultId: (int) $row->id,
                termId: (int) $row->term_id,
                subjectId: (int) $row->subject_id,
                weightedTotal: $row->weighted_total !== null ? (string) $row->weighted_total : null,
                passFail: $row->pass_fail !== null ? (int) $row->pass_fail : null,
                incomplete: (bool) $row->incomplete,
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

    public function nextResultVersion(int $schoolId, int $enrollmentId, int $academicYearId): int
    {
        $max = DB::table(SchemaHelper::qualified('results', 'annual_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->max('result_version');

        return ((int) $max) + 1;
    }

    public function insertOperational(PersistOperationalAnnualResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'annual_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('is_current_operational', true)
            ->update([
                'is_current_operational' => false,
                'lifecycle_status' => ResultsLifecycleStatus::Superseded->value,
                'superseded_at' => $data->calculatedAt,
                'updated_at' => $data->calculatedAt,
            ]);

        return (int) DB::table($table)->insertGetId([
            'school_id' => $data->schoolId,
            'enrollment_id' => $data->enrollmentId,
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => ResultsLifecycleStatus::Calculated->value,
            'is_official' => false,
            'is_current_operational' => true,
            'is_current_official' => false,
            'subjects_counted' => $data->subjectsCounted,
            'subjects_passed' => $data->subjectsPassed,
            'subjects_incomplete' => $data->subjectsIncomplete,
            'average_weighted_total' => $data->averageWeightedTotal,
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

    public function insertOfficial(PersistOperationalAnnualResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'annual_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('is_current_official', true)
            ->update([
                'is_current_official' => false,
                'lifecycle_status' => ResultsLifecycleStatus::Superseded->value,
                'superseded_at' => $data->calculatedAt,
                'updated_at' => $data->calculatedAt,
            ]);

        return (int) DB::table($table)->insertGetId([
            'school_id' => $data->schoolId,
            'enrollment_id' => $data->enrollmentId,
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'subjects_counted' => $data->subjectsCounted,
            'subjects_passed' => $data->subjectsPassed,
            'subjects_incomplete' => $data->subjectsIncomplete,
            'average_weighted_total' => $data->averageWeightedTotal,
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
        int $academicYearId,
    ): ?CurrentAnnualResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $academicYearId, 'is_current_operational');
    }

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentAnnualResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $academicYearId, 'is_current_official');
    }

    private function findCurrent(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        string $flag,
    ): ?CurrentAnnualResultSnapshot {
        $row = DB::table(SchemaHelper::qualified('results', 'annual_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where($flag, true)
            ->first([
                'id',
                'result_version',
                'source_fingerprint',
                'average_weighted_total',
                'incomplete',
            ]);

        if ($row === null) {
            return null;
        }

        return new CurrentAnnualResultSnapshot(
            id: (int) $row->id,
            resultVersion: (int) $row->result_version,
            sourceFingerprint: (string) $row->source_fingerprint,
            averageWeightedTotal: $row->average_weighted_total !== null ? (string) $row->average_weighted_total : null,
            incomplete: (bool) $row->incomplete,
        );
    }
}
