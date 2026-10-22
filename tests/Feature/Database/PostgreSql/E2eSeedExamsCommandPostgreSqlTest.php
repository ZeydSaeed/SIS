<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * sis:e2e-seed-exams fails closed on any database that is not a disposable sis_e2e_* copy — the PHPUnit database
 * included — and writes nothing there.
 */
final class E2eSeedExamsCommandPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function refuses_a_non_disposable_database_and_writes_nothing(): void
    {
        $count = fn (string $schema, string $table): int => DB::table(SchemaHelper::qualified($schema, $table))->count();
        $before = [$count('exams', 'exams'), $count('exams', 'exam_types'), $count('teachers', 'teachers'), DB::table('users')->count()];

        $exit = Artisan::call('sis:e2e-seed-exams');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('is not a disposable e2e copy', Artisan::output());
        $this->assertSame($before, [$count('exams', 'exams'), $count('exams', 'exam_types'), $count('teachers', 'teachers'), DB::table('users')->count()]);
    }
}
