<?php

namespace App\Infrastructure\Persistence\Results;

use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentResultPublicationRepository implements ResultPublicationRepositoryInterface
{
    public function activeOverlapping(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId = null, ?int $subjectId = null): array
    {
        return DB::table($this->table())
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('class_id', $classId)
            ->where('kind', $kind)->where('status', 'published')
            ->when($termId !== null, static fn ($q) => $q->where('term_id', $termId)->where('subject_id', $subjectId))
            ->when($sectionId !== null, static fn ($q) => $q->where(static fn ($w) => $w->whereNull('section_id')->orWhere('section_id', $sectionId)))
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function insert(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, string $kind, ?int $termId, ?int $subjectId, array $snapshot, string $fingerprint, ?int $userId, ?string $correlationId): int
    {
        return (int) DB::table($this->table())->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'kind' => $kind,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'participant_count' => count($snapshot),
            'source_fingerprint' => $fingerprint,
            'published_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'published_by' => $userId,
            'correlation_id' => $correlationId,
        ]);
    }

    public function withdraw(int $schoolId, int $publicationId, ?int $userId, string $reason): bool
    {
        return DB::table($this->table())
            ->where('id', $publicationId)->where('school_id', $schoolId)->where('status', 'published')
            ->update(['status' => 'withdrawn', 'withdrawn_by' => $userId, 'withdrawn_at' => now(), 'withdraw_reason' => $reason, 'updated_at' => now()]) === 1;
    }

    public function isPublished(int $schoolId, int $academicYearId, int $enrollmentId, string $kind, int $version, ?int $termId = null, ?int $subjectId = null): bool
    {
        return DB::table($this->table())
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->where('kind', $kind)->where('status', 'published')
            ->when($kind === 'term', static fn ($q) => $q->where('term_id', $termId)->where('subject_id', $subjectId))
            ->whereRaw('published_snapshot @> ?::jsonb', [json_encode([['enrollment_id' => $enrollmentId, 'version' => $version]], JSON_THROW_ON_ERROR)])
            ->exists();
    }

    public function activeForClass(int $schoolId, int $academicYearId, int $classId): array
    {
        return DB::table($this->table())
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('class_id', $classId)->where('status', 'published')
            ->orderBy('id')->get(['id', 'kind', 'section_id', 'term_id', 'subject_id', 'participant_count', 'published_at'])
            ->map(fn ($r): array => [
                'id' => (int) $r->id, 'kind' => (string) $r->kind,
                'section_id' => $r->section_id === null ? null : (int) $r->section_id,
                'term_id' => $r->term_id === null ? null : (int) $r->term_id,
                'subject_id' => $r->subject_id === null ? null : (int) $r->subject_id,
                'participants' => (int) $r->participant_count, 'published_at' => (string) $r->published_at,
                'drifted' => $this->drifted((int) $r->id, (string) $r->kind),
            ])->all();
    }

    /** Snapshot entries whose enrollment no longer has that exact version as its current official result / transcript. */
    private function drifted(int $publicationId, string $kind): int
    {
        [$table, $current, $version, $scope] = match ($kind) {
            'gpa' => ['gpa_results', 'r.is_current_official', 'r.result_version', ''],
            'term' => ['term_results', 'r.is_current_official', 'r.result_version', 'AND r.term_id = p.term_id AND r.subject_id = p.subject_id'],
            'transcript' => ['transcripts', 'r.is_current', 'r.transcript_version', ''],
            default => ['annual_results', 'r.is_current_official', 'r.result_version', ''],
        };
        $results = SchemaHelper::qualified('results', $table);
        $publications = $this->table();

        return (int) DB::selectOne("
            SELECT count(*) AS n
            FROM {$publications} p
            CROSS JOIN LATERAL jsonb_array_elements(p.published_snapshot) AS e
            WHERE p.id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM {$results} r
                  WHERE r.enrollment_id = (e->>'enrollment_id')::bigint
                    AND r.academic_year_id = p.academic_year_id
                    AND {$current}
                    AND {$version} = (e->>'version')::int
                    {$scope}
              )
        ", [$publicationId])->n;
    }

    private function table(): string
    {
        return SchemaHelper::qualified('results', 'result_publications');
    }
}
