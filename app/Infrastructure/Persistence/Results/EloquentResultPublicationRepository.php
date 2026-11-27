<?php

namespace App\Infrastructure\Persistence\Results;

use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Results\Exceptions\ResultPublicationException;
use App\Domain\Results\ValueObjects\GpaScope;
use Illuminate\Database\UniqueConstraintViolationException;
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
        try {
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
        } catch (UniqueConstraintViolationException) {
            // Two requests passed the overlap check together; the partial unique index admitted only one.
            throw ResultPublicationException::alreadyPublished();
        }
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

    public function activeWithDrift(int $schoolId): array
    {
        return DB::table($this->table())->where('school_id', $schoolId)->where('status', 'published')
            ->orderBy('academic_year_id')->orderBy('kind')->orderBy('id')
            ->get(['id', 'academic_year_id', 'kind', 'class_id', 'section_id', 'term_id', 'subject_id', 'participant_count', 'published_at'])
            ->map(fn (object $r): array => [
                'publication_id' => (int) $r->id, 'academic_year_id' => (int) $r->academic_year_id, 'kind' => (string) $r->kind,
                'class_id' => (int) $r->class_id, 'section_id' => $r->section_id === null ? null : (int) $r->section_id,
                'term_id' => $r->term_id === null ? null : (int) $r->term_id, 'subject_id' => $r->subject_id === null ? null : (int) $r->subject_id,
                'participants' => (int) $r->participant_count, 'drifted' => $this->drifted((int) $r->id, (string) $r->kind), 'published_at' => (string) $r->published_at,
            ])->all();
    }

    public function activeForClass(int $schoolId, int $academicYearId, int $classId): array
    {
        return DB::table($this->table().' as p')->leftJoin('public.users as u', 'u.id', '=', 'p.published_by')
            ->where('p.school_id', $schoolId)->where('p.academic_year_id', $academicYearId)->where('p.class_id', $classId)->where('p.status', 'published')
            ->orderBy('p.id')->get(['p.id', 'p.kind', 'p.section_id', 'p.term_id', 'p.subject_id', 'p.participant_count', 'p.published_at', 'u.name as published_by_name'])
            ->map(fn ($r): array => [
                'id' => (int) $r->id, 'kind' => (string) $r->kind,
                'section_id' => $r->section_id === null ? null : (int) $r->section_id,
                'term_id' => $r->term_id === null ? null : (int) $r->term_id,
                'subject_id' => $r->subject_id === null ? null : (int) $r->subject_id,
                'participants' => (int) $r->participant_count, 'published_at' => (string) $r->published_at,
                'published_by_name' => $r->published_by_name === null ? null : (string) $r->published_by_name,
                'drifted' => $this->drifted((int) $r->id, (string) $r->kind),
            ])->all();
    }

    /** Snapshot entries whose enrollment no longer has that exact version as its current official result / transcript. */
    public function lockSnapshot(int $schoolId, int $academicYearId, string $kind, array $snapshot, ?int $termId = null, ?int $subjectId = null): bool
    {
        if ($snapshot === []) {
            return false;
        }
        [$table, $current, $version] = $this->officialColumns($kind);
        $results = SchemaHelper::qualified('results', $table);
        $entries = json_encode(array_map(static fn (array $s): array => ['enrollment_id' => $s['enrollment_id'], 'version' => $s['version']], $snapshot), JSON_THROW_ON_ERROR);
        // Same rows the snapshot was built from: a term of one subject, or the academic-year GPA (not term GPAs).
        [$scope, $bindings] = match ($kind) {
            'term' => ['AND r.term_id = ? AND r.subject_id = ?', [$entries, $schoolId, $academicYearId, $termId, $subjectId]],
            'gpa' => ['AND r.gpa_scope = ?', [$entries, $schoolId, $academicYearId, GpaScope::AcademicYear->value]],
            default => ['', [$entries, $schoolId, $academicYearId]],
        };

        $locked = DB::select("
            SELECT r.id
            FROM {$results} r
            JOIN jsonb_to_recordset(?::jsonb) AS e(enrollment_id bigint, version int)
              ON r.enrollment_id = e.enrollment_id AND {$version} = e.version
            WHERE r.school_id = ? AND r.academic_year_id = ? AND {$current} {$scope}
            FOR SHARE OF r
        ", $bindings);

        return count($locked) === count($snapshot);
    }

    /** @return array{string, string, string} table, current flag, version column of the official result of the kind */
    private function officialColumns(string $kind): array
    {
        return match ($kind) {
            'gpa' => ['gpa_results', 'r.is_current_official', 'r.result_version'],
            'term' => ['term_results', 'r.is_current_official', 'r.result_version'],
            'transcript' => ['transcripts', 'r.is_current', 'r.transcript_version'],
            default => ['annual_results', 'r.is_current_official', 'r.result_version'],
        };
    }

    private function drifted(int $publicationId, string $kind): int
    {
        [$table, $current, $version] = $this->officialColumns($kind);
        $scope = $kind === 'term' ? 'AND r.term_id = p.term_id AND r.subject_id = p.subject_id' : '';
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
