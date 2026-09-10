<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\SeedsGraduationConcurrencyGraph;

/**
 * Phase 3C.12B — parallel-process concurrency (REAL CONCURRENCY on Windows-capable Process).
 */
class GraduationParallelProcessConcurrencyPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use SeedsGraduationConcurrencyGraph;

    /**
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    #[Test]
    public function parallel_processes_cannot_insert_duplicate_completion_outcomes(): void
    {
        $g = $this->seedGraduationConcurrencyGraph();
        $barrier = storage_path('framework/testing/grad-race-'.uniqid('', true));
        @unlink($barrier);

        $script = base_path('tests/Support/Database/graduation_race_completion_outcome.php');
        $env = [
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
        ];

        $args = [
            PHP_BINARY,
            $script,
            (string) $g['school_a'],
            (string) $g['enrollment_a'],
            (string) $g['student_a'],
            (string) $g['year_id'],
            $barrier,
        ];

        $p1 = new Process($args, base_path(), $env);
        $p2 = new Process($args, base_path(), $env);
        $p1->start();
        $p2->start();
        // Release both workers together.
        file_put_contents($barrier, 'go');
        $p1->wait();
        $p2->wait();
        @unlink($barrier);

        $codes = [$p1->getExitCode(), $p2->getExitCode()];
        sort($codes);

        $this->assertSame([0, 1], $codes, 'Exactly one insert OK and one unique_violation. got='.json_encode([
            'c1' => $p1->getExitCode(),
            'c2' => $p2->getExitCode(),
            'e1' => $p1->getErrorOutput(),
            'e2' => $p2->getErrorOutput(),
        ]));

        $this->assertSame(1, (int) DB::table('graduation.completion_outcomes')
            ->where('school_id', $g['school_a'])
            ->where('enrollment_id', $g['enrollment_a'])
            ->count());
    }

    #[Test]
    public function five_repeat_parallel_completion_outcome_races_are_deterministic(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $g = $this->seedGraduationConcurrencyGraph();
            $barrier = storage_path('framework/testing/grad-race-r'.$i.'-'.uniqid('', true));
            @unlink($barrier);
            $script = base_path('tests/Support/Database/graduation_race_completion_outcome.php');
            $env = [
                'DB_HOST' => (string) config('database.connections.pgsql.host'),
                'DB_PORT' => (string) config('database.connections.pgsql.port'),
                'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
                'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
                'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            ];
            $args = [
                PHP_BINARY,
                $script,
                (string) $g['school_a'],
                (string) $g['enrollment_a'],
                (string) $g['student_a'],
                (string) $g['year_id'],
                $barrier,
            ];
            $p1 = new Process($args, base_path(), $env);
            $p2 = new Process($args, base_path(), $env);
            $p1->start();
            $p2->start();
            file_put_contents($barrier, 'go');
            $p1->wait();
            $p2->wait();
            @unlink($barrier);

            $codes = [$p1->getExitCode(), $p2->getExitCode()];
            sort($codes);
            $this->assertSame([0, 1], $codes, "Run {$i} failed");
            $this->assertSame(1, (int) DB::table('graduation.completion_outcomes')
                ->where('school_id', $g['school_a'])
                ->where('enrollment_id', $g['enrollment_a'])
                ->count(), "Run {$i} row count");
        }
    }
}
