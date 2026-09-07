<?php

namespace Tests\Feature\Security;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostgreSqlRlsFailClosedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function null_school_context_hides_enrollment_rows(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for RLS validation.');
        }

        $countWithNullContext = (int) DB::selectOne('
            SELECT COUNT(*) AS aggregate
            FROM enrollment.enrollments
        ')->aggregate;

        $this->assertSame(0, $countWithNullContext);
    }

    #[Test]
    public function school_context_filters_enrollment_rows_and_clears_after_request(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for RLS validation.');
        }

        DB::statement("SELECT set_config('app.current_school_id', '999999', false)");

        $countWrongSchool = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM enrollment.enrollments')->aggregate;
        $this->assertSame(0, $countWrongSchool);

        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        $countCleared = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM enrollment.enrollments')->aggregate;
        $this->assertSame(0, $countCleared);
    }
}
