<?php

namespace App\Infrastructure\Persistence\Results;

use App\Database\SchemaHelper;
use App\Domain\Results\Data\IssuedTranscriptRead;
use App\Domain\Results\Data\OfficialTranscriptSource;
use App\Domain\Results\Data\PersistIssuedTranscriptData;
use App\Domain\Results\Repositories\TranscriptRepositoryInterface;
use App\Domain\Results\ValueObjects\GpaScope;
use App\Domain\Results\ValueObjects\ResultsLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTranscriptRepository implements TranscriptRepositoryInterface
{
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

    public function findOfficialYearGpaSource(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?OfficialTranscriptSource {
        $gpa = SchemaHelper::qualified('results', 'gpa_results');
        $annual = SchemaHelper::qualified('results', 'annual_results');

        $row = DB::table("{$gpa} as g")
            ->leftJoin("{$annual} as a", 'a.id', '=', 'g.source_annual_result_id')
            ->where('g.school_id', $schoolId)
            ->where('g.enrollment_id', $enrollmentId)
            ->where('g.academic_year_id', $academicYearId)
            ->where('g.gpa_scope', GpaScope::AcademicYear->value)
            ->where('g.is_current_official', true)
            ->first([
                'g.id as gpa_result_id',
                'g.gpa_value',
                'g.scale_code',
                'g.source_fingerprint as gpa_fingerprint',
                'g.source_annual_result_id',
                'a.source_fingerprint as annual_fingerprint',
            ]);

        if ($row === null) {
            return null;
        }

        return new OfficialTranscriptSource(
            gpaResultId: (int) $row->gpa_result_id,
            gpaValue: $row->gpa_value !== null ? (string) $row->gpa_value : null,
            scaleCode: (string) $row->scale_code,
            gpaFingerprint: (string) $row->gpa_fingerprint,
            annualResultId: $row->source_annual_result_id !== null ? (int) $row->source_annual_result_id : null,
            annualFingerprint: $row->annual_fingerprint !== null ? (string) $row->annual_fingerprint : null,
        );
    }

    public function nextTranscriptVersion(int $schoolId, int $enrollmentId, int $academicYearId): int
    {
        $max = DB::table(SchemaHelper::qualified('results', 'transcripts'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->max('transcript_version');

        return ((int) $max) + 1;
    }

    public function insertIssued(PersistIssuedTranscriptData $data): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $data->schoolId]);

        $table = SchemaHelper::qualified('results', 'transcripts');

        DB::table($table)
            ->where('school_id', $data->schoolId)
            ->where('enrollment_id', $data->enrollmentId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('is_current', true)
            ->update([
                'is_current' => false,
                'lifecycle_status' => ResultsLifecycleStatus::Superseded->value,
                'superseded_at' => $data->issuedAt,
                'updated_at' => $data->issuedAt,
            ]);

        return (int) DB::table($table)->insertGetId([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'enrollment_id' => $data->enrollmentId,
            'academic_year_id' => $data->academicYearId,
            'transcript_version' => $data->transcriptVersion,
            'transcript_number' => $data->transcriptNumber,
            'lifecycle_status' => ResultsLifecycleStatus::Finalized->value,
            'is_current' => true,
            'storage_key' => $data->storageKey,
            'payload_hash' => $data->payloadHash,
            'source_fingerprint' => $data->sourceFingerprint,
            'policy_pin' => json_encode($data->policyPin, JSON_THROW_ON_ERROR),
            'issued_at' => $data->issuedAt,
            'issued_by' => $data->issuedBy,
            'superseded_at' => null,
            'correlation_id' => $data->correlationId,
            'created_at' => $data->issuedAt,
            'updated_at' => $data->issuedAt,
        ]);
    }

    public function findCurrentIssued(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?IssuedTranscriptRead {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $row = DB::table(SchemaHelper::qualified('results', 'transcripts'))
            ->where('school_id', $schoolId)
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('is_current', true)
            ->first([
                'id', 'student_id', 'transcript_version', 'transcript_number',
                'payload_hash', 'storage_key', 'issued_at',
            ]);

        if ($row === null) {
            return null;
        }

        return new IssuedTranscriptRead(
            id: (int) $row->id,
            studentId: (int) $row->student_id,
            transcriptVersion: (int) $row->transcript_version,
            transcriptNumber: (string) $row->transcript_number,
            payloadHash: (string) $row->payload_hash,
            storageKey: $row->storage_key !== null ? (string) $row->storage_key : null,
            issuedAt: (string) $row->issued_at,
        );
    }
}
