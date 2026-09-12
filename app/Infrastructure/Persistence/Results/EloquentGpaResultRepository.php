<?php

namespace App\Infrastructure\Persistence\Results;

use App\Database\SchemaHelper;
use App\Domain\Results\Data\CurrentGpaResultSnapshot;
use App\Domain\Results\Data\OfficialAnnualGpaSource;
use App\Domain\Results\Data\PersistOperationalGpaResultData;
use App\Domain\Results\Repositories\GpaResultRepositoryInterface;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentGpaResultRepository implements GpaResultRepositoryInterface
{
    public function findOfficialAnnualSource(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?OfficialAnnualGpaSource {
        $row = DB::table(SchemaHelper::qualified('results', 'annual_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('is_current_official', true)
            ->first(['id', 'average_weighted_total', 'incomplete', 'source_fingerprint']);

        if ($row === null) {
            return null;
        }

        return new OfficialAnnualGpaSource(
            annualResultId: (int) $row->id,
            averageWeightedTotal: $row->average_weighted_total !== null ? (string) $row->average_weighted_total : null,
            incomplete: (bool) $row->incomplete,
            sourceFingerprint: (string) $row->source_fingerprint,
        );
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
        $max = DB::table(SchemaHelper::qualified('results', 'gpa_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('gpa_scope', GpaScope::AcademicYear->value)
            ->max('result_version');

        return ((int) $max) + 1;
    }

    public function insertOperational(PersistOperationalGpaResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'gpa_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('gpa_scope', GpaScope::AcademicYear->value)
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
            'gpa_scope' => GpaScope::AcademicYear->value,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => ResultsLifecycleStatus::Calculated->value,
            'is_official' => false,
            'is_current_operational' => true,
            'is_current_official' => false,
            'gpa_value' => $data->gpaValue,
            'scale_code' => $data->scaleCode,
            'source_annual_result_id' => $data->sourceAnnualResultId,
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

    public function insertOfficial(PersistOperationalGpaResultData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'gpa_results');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('gpa_scope', GpaScope::AcademicYear->value)
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
            'gpa_scope' => GpaScope::AcademicYear->value,
            'result_version' => $data->resultVersion,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_official' => true,
            'is_current_operational' => false,
            'is_current_official' => true,
            'gpa_value' => $data->gpaValue,
            'scale_code' => $data->scaleCode,
            'source_annual_result_id' => $data->sourceAnnualResultId,
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
    ): ?CurrentGpaResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $academicYearId, 'is_current_operational');
    }

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentGpaResultSnapshot {
        return $this->findCurrent($schoolId, $enrollmentId, $academicYearId, 'is_current_official');
    }

    private function findCurrent(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        string $flag,
    ): ?CurrentGpaResultSnapshot {
        $row = DB::table(SchemaHelper::qualified('results', 'gpa_results'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('gpa_scope', GpaScope::AcademicYear->value)
            ->where($flag, true)
            ->first(['id', 'result_version', 'source_fingerprint', 'gpa_value', 'incomplete']);

        if ($row === null) {
            return null;
        }

        return new CurrentGpaResultSnapshot(
            id: (int) $row->id,
            resultVersion: (int) $row->result_version,
            sourceFingerprint: (string) $row->source_fingerprint,
            gpaValue: $row->gpa_value !== null ? (string) $row->gpa_value : null,
            incomplete: (bool) $row->incomplete,
        );
    }
}
