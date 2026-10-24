<?php

namespace Tests\Support\Results;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/** Test helper: an active publication that snapshots the given version of an enrollment's official result of the kind. */
trait PublishesOfficialResults
{
    protected function publishOfficialResult(int $schoolId, int $yearId, int $enrollmentId, string $kind, int $version = 1, ?int $termId = null, ?int $subjectId = null): int
    {
        return (int) DB::table(SchemaHelper::qualified('results', 'result_publications'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'class_id' => (int) $this->createClassForSchool($schoolId, $yearId)->id,
            'kind' => $kind,
            'term_id' => $termId,
            'subject_id' => $subjectId,
            'participant_count' => 1,
            'source_fingerprint' => str_repeat('b', 64),
            'published_snapshot' => json_encode([['enrollment_id' => $enrollmentId, 'version' => $version, 'value' => null]], JSON_THROW_ON_ERROR),
        ]);
    }
}
