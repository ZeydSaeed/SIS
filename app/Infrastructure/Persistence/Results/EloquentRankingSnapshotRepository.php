<?php

namespace App\Infrastructure\Persistence\Results;

use App\Database\SchemaHelper;
use App\Domain\Results\Data\CurrentRankingSnapshotRead;
use App\Domain\Results\Data\PersistRankingSnapshotData;
use App\Domain\Results\Data\RankingParticipant;
use App\Domain\Results\Repositories\RankingSnapshotRepositoryInterface;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentRankingSnapshotRepository implements RankingSnapshotRepositoryInterface
{
    public function listOfficialYearGpaParticipants(
        int $schoolId,
        int $academicYearId,
        int $classId,
    ): array {
        $gpa = SchemaHelper::qualified('results', 'gpa_results');
        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');

        $rows = DB::table("{$gpa} as g")
            ->join("{$enrollments} as e", function ($join) {
                $join->on('e.id', '=', 'g.enrollment_id')
                    ->on('e.school_id', '=', 'g.school_id')
                    ->on('e.academic_year_id', '=', 'g.academic_year_id');
            })
            ->where('g.school_id', $schoolId)
            ->where('g.academic_year_id', $academicYearId)
            ->where('g.gpa_scope', GpaScope::AcademicYear->value)
            ->where('g.is_current_official', true)
            ->where('e.class_id', $classId)
            ->select([
                'g.enrollment_id',
                'g.student_id',
                'g.id as gpa_result_id',
                'g.gpa_value',
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = new RankingParticipant(
                enrollmentId: (int) $row->enrollment_id,
                studentId: (int) $row->student_id,
                gpaResultId: (int) $row->gpa_result_id,
                metricValue: $row->gpa_value !== null ? (string) $row->gpa_value : null,
            );
        }

        return $out;
    }

    public function nextSnapshotVersion(int $schoolId, int $academicYearId, int $classId): int
    {
        $max = DB::table(SchemaHelper::qualified('results', 'ranking_snapshots'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->max('snapshot_version');

        return ((int) $max) + 1;
    }

    public function insertSnapshot(PersistRankingSnapshotData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $snapshots = SchemaHelper::qualified('results', 'ranking_snapshots');
        $entries = SchemaHelper::qualified('results', 'ranking_snapshot_entries');

        DB::table($snapshots)
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('class_id', $data->classId)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'lifecycle_status' => ResultsLifecycleStatus::Superseded->value,
                'superseded_at' => $data->calculatedAt,
                'updated_at' => $data->calculatedAt,
            ]);

        $snapshotId = (int) DB::table($snapshots)->insertGetId([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'class_id' => $data->classId,
            'snapshot_version' => $data->snapshotVersion,
            'lifecycle_status' => ResultsLifecycleStatus::Calculated->value,
            'is_current' => true,
            'metric_code' => 'YEAR_GPA_PERCENT_100',
            'participant_count' => $data->participantCount,
            'source_fingerprint' => $data->sourceFingerprint,
            'policy_pin' => json_encode($data->policyPin, JSON_THROW_ON_ERROR),
            'calculated_at' => $data->calculatedAt,
            'superseded_at' => null,
            'correlation_id' => $data->correlationId,
            'created_by' => $data->createdBy,
            'created_at' => $data->calculatedAt,
            'updated_at' => $data->calculatedAt,
        ]);

        foreach ($data->entries as $entry) {
            DB::table($entries)->insert([
                'ranking_snapshot_id' => $snapshotId,
                'school_id' => $data->schoolId,
                'enrollment_id' => $entry->enrollmentId,
                'student_id' => $entry->studentId,
                'gpa_result_id' => $entry->gpaResultId,
                'metric_value' => $entry->metricValue,
                'rank_position' => $entry->rankPosition,
                'created_at' => $data->calculatedAt,
            ]);
        }

        return $snapshotId;
    }

    public function findCurrentSnapshot(
        int $schoolId,
        int $academicYearId,
        int $classId,
    ): ?CurrentRankingSnapshotRead {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $header = DB::table(SchemaHelper::qualified('results', 'ranking_snapshots'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('class_id', $classId)
            ->where('is_current', true)
            ->first(['id', 'snapshot_version', 'metric_code', 'participant_count']);

        if ($header === null) {
            return null;
        }

        $entries = DB::table(SchemaHelper::qualified('results', 'ranking_snapshot_entries'))
            ->where('ranking_snapshot_id', $header->id)
            ->orderBy('rank_position')
            ->orderBy('enrollment_id')
            ->get(['enrollment_id', 'student_id', 'gpa_result_id', 'metric_value', 'rank_position']);

        $mapped = [];
        foreach ($entries as $e) {
            $mapped[] = [
                'enrollment_id' => (int) $e->enrollment_id,
                'student_id' => (int) $e->student_id,
                'gpa_result_id' => (int) $e->gpa_result_id,
                'metric_value' => $e->metric_value !== null ? (string) $e->metric_value : null,
                'rank_position' => (int) $e->rank_position,
            ];
        }

        return new CurrentRankingSnapshotRead(
            id: (int) $header->id,
            snapshotVersion: (int) $header->snapshot_version,
            metricCode: (string) $header->metric_code,
            participantCount: (int) $header->participant_count,
            entries: $mapped,
        );
    }
}
