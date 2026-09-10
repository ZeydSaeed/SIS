<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

/**
 * Phase 3C.12B — constraint / retry / tenant proofs against Graduation on sis_test.
 *
 * Dual-PDO cases here are SERIALIZED UNIQUENESS (A commits, then B insert → 23505).
 * True overlapping process races live in GraduationParallelProcessConcurrencyPostgreSqlTest.
 */
class GraduationConcurrencyIdempotencyPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    /**
     * Disable wrapping transactions so fixture rows are visible to peer PDO connections.
     *
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    #[Test]
    public function concurrent_duplicate_completion_outcome_yields_exactly_one_row(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO graduation.completion_outcomes
            (school_id, enrollment_id, student_id, academic_year_id, specialization_id, created_at)
            VALUES (?, ?, ?, ?, NULL, NOW())';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();

        $stmtA = $pdoA->prepare($sql);
        $stmtA->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);

        $errorCode = null;
        $stmtB = $pdoB->prepare($sql);
        try {
            // Overlap: B insert while A still open (may block until A commits).
            $pdoA->commit();
            $stmtB->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
            if ($pdoA->inTransaction()) {
                $pdoA->commit();
            }
        }

        $this->assertSame('23505', (string) $errorCode, 'Expected unique_violation on concurrent duplicate outcome');

        $count = (int) DB::table('graduation.completion_outcomes')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function different_idempotency_keys_cannot_create_duplicate_completion_outcome(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $this->setSchoolContext(DB::connection()->getPdo(), $g['school_a']);

        // Simulate two different keys racing the same business identity via DB uniqueness.
        DB::table('audit.idempotency_keys')->insert([
            'key' => 'key-x-'.uniqid(),
            'command_name' => 'Graduation.PublishCompletion',
            'response_payload' => json_encode(['note' => 'a']),
            'created_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        DB::table('audit.idempotency_keys')->insert([
            'key' => 'key-y-'.uniqid(),
            'command_name' => 'Graduation.PublishCompletion',
            'response_payload' => json_encode(['note' => 'b']),
            'created_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO graduation.completion_outcomes
            (school_id, enrollment_id, student_id, academic_year_id, specialization_id, created_at)
            VALUES (?, ?, ?, ?, NULL, NOW())';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();
        $pdoA->prepare($sql)->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);

        $errorCode = null;
        try {
            $pdoA->commit();
            $pdoB->prepare($sql)->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
        }

        $this->assertSame('23505', $errorCode);
        $this->assertSame(1, (int) DB::table('graduation.completion_outcomes')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count());
    }

    #[Test]
    public function concurrent_current_official_versions_yield_exactly_one_current(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $g['school_a']]);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO graduation.completion_outcome_versions
            (school_id, completion_outcome_id, version_no, lifecycle_status, evaluation_status, eligibility_status,
             eligibility_policy_version_id, calculation_version, is_current_official, created_at)
            VALUES (?, ?, ?, 2, 2, 2, ?, ?, true, NOW())';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();
        $pdoA->prepare($sql)->execute([$g['school_a'], $outcomeId, 1, $g['policy_version_id'], 'calc-1']);

        $errorCode = null;
        try {
            $pdoA->commit();
            $pdoB->prepare($sql)->execute([$g['school_a'], $outcomeId, 2, $g['policy_version_id'], 'calc-2']);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
        }

        $this->assertSame('23505', $errorCode, 'Partial UNIQUE on is_current_official must reject second current');
        $this->assertSame(1, (int) DB::table('graduation.completion_outcome_versions')
            ->where('completion_outcome_id', $outcomeId)
            ->where('is_current_official', true)
            ->count());
    }

    #[Test]
    public function concurrent_duplicate_version_no_is_rejected(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $g['school_a']]);

        $outcomeId = (int) DB::table('graduation.completion_outcomes')->insertGetId([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO graduation.completion_outcome_versions
            (school_id, completion_outcome_id, version_no, lifecycle_status, evaluation_status, eligibility_status,
             eligibility_policy_version_id, calculation_version, is_current_official, created_at)
            VALUES (?, ?, 1, 1, 1, 1, ?, ?, false, NOW())';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();
        $pdoA->prepare($sql)->execute([$g['school_a'], $outcomeId, $g['policy_version_id'], 'c1']);

        $errorCode = null;
        try {
            $pdoA->commit();
            $pdoB->prepare($sql)->execute([$g['school_a'], $outcomeId, $g['policy_version_id'], 'c2']);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
        }

        $this->assertSame('23505', $errorCode);
        $this->assertSame(1, (int) DB::table('graduation.completion_outcome_versions')
            ->where('completion_outcome_id', $outcomeId)
            ->where('version_no', 1)
            ->count());
    }

    #[Test]
    public function concurrent_graduation_awards_same_enrollment_yield_exactly_one(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();
        $this->setSchoolContext($pdoA, $g['school_a']);
        $this->setSchoolContext($pdoB, $g['school_a']);

        $sql = 'INSERT INTO graduation.graduation_awards
            (school_id, enrollment_id, student_id, academic_year_id, specialization_id, created_at)
            VALUES (?, ?, ?, ?, NULL, NOW())';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();
        $pdoA->prepare($sql)->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);

        $errorCode = null;
        try {
            $pdoA->commit();
            $pdoB->prepare($sql)->execute([$g['school_a'], $g['enrollment_a'], $g['student_a'], $g['year_id']]);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
        }

        $this->assertSame('23505', $errorCode);
        $this->assertSame(1, (int) DB::table('graduation.graduation_awards')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count());
    }

    #[Test]
    public function retry_after_commit_cannot_duplicate_completion_outcome(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $g['school_a']]);

        DB::table('graduation.completion_outcomes')->insert([
            'school_id' => $g['school_a'],
            'enrollment_id' => $g['enrollment_a'],
            'student_id' => $g['student_a'],
            'academic_year_id' => $g['year_id'],
            'created_at' => now(),
        ]);

        $failed = false;
        try {
            DB::table('graduation.completion_outcomes')->insert([
                'school_id' => $g['school_a'],
                'enrollment_id' => $g['enrollment_a'],
                'student_id' => $g['student_a'],
                'academic_year_id' => $g['year_id'],
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $this->assertSame(1, (int) DB::table('graduation.completion_outcomes')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count());
    }

    #[Test]
    public function cross_school_enrollment_pairing_is_rejected_by_composite_fk(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $g['school_a']]);

        $failed = false;
        try {
            DB::table('graduation.completion_outcomes')->insert([
                'school_id' => $g['school_a'],
                'enrollment_id' => $g['enrollment_b'], // school B enrollment
                'student_id' => $g['student_b'],
                'academic_year_id' => $g['year_id'],
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Composite FK must reject school A + enrollment B');
    }

    #[Test]
    public function concurrent_idempotency_key_primary_key_allows_only_one_row(): void
    {
        $key = 'grad-idem-'.uniqid('', true);
        $command = 'Graduation.IssueAward';

        $pdoA = $this->openPgsqlPdo();
        $pdoB = $this->openPgsqlPdo();

        $sql = 'INSERT INTO audit.idempotency_keys (key, command_name, response_payload, created_at, expires_at)
            VALUES (?, ?, ?, NOW(), NOW() + interval \'1 day\')';

        $pdoA->beginTransaction();
        $pdoB->beginTransaction();
        $pdoA->prepare($sql)->execute([$key, $command, '{"ok":1}']);

        $errorCode = null;
        try {
            $pdoA->commit();
            $pdoB->prepare($sql)->execute([$key, $command, '{"ok":2}']);
            $pdoB->commit();
        } catch (PDOException $e) {
            $errorCode = (string) $e->getCode();
            if ($pdoB->inTransaction()) {
                $pdoB->rollBack();
            }
        }

        $this->assertSame('23505', $errorCode);
        $this->assertSame(1, (int) DB::table('audit.idempotency_keys')
            ->where('key', $key)
            ->where('command_name', $command)
            ->count());
    }

    #[Test]
    public function graduation_tables_remain_rls_force_enabled(): void
    {
        $row = DB::selectOne("
            SELECT COUNT(*)::int AS c
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'graduation' AND c.relkind = 'r'
              AND c.relrowsecurity AND c.relforcerowsecurity
        ");
        $this->assertSame(14, (int) $row->c);
    }
}
