<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Intelligence\Guardian\SchemaGuardian;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * The schema guardian (queued after migrations, daily) looked foreign keys up through an information_schema join that
 * multiplied rows across partitions — 312k rows for 7.8k keys, over the worker's memory (found by the e2e suite). It
 * now asks pg_constraint for the one column; it must still see a present key and report a missing one.
 */
final class SchemaGuardianForeignKeyPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function the_enrollment_student_key_is_seen_when_present_and_reported_when_missing(): void
    {
        $missing = static fn (): bool => app(SchemaGuardian::class)->validate()
            ->contains(static fn (array $f): bool => $f['code'] === 'MISSING_FOREIGN_KEY' && $f['table'] === 'enrollment.enrollments');
        $this->assertFalse($missing());

        $constraint = DB::selectOne("SELECT c.conname FROM pg_constraint c JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
            WHERE c.contype = 'f' AND c.conrelid = 'enrollment.enrollments'::regclass AND a.attname = 'student_id'")->conname;
        // Inside the test's transaction: rolled back afterwards.
        DB::statement('ALTER TABLE enrollment.enrollments DROP CONSTRAINT "'.$constraint.'"');
        $this->assertTrue($missing());
    }
}
