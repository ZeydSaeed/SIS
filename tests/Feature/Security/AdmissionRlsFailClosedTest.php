<?php

namespace Tests\Feature\Security;

use App\Database\SchemaHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdmissionRlsFailClosedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function null_school_context_hides_admission_periods(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for RLS validation.');
        }

        $count = (int) DB::selectOne('
            SELECT COUNT(*) AS aggregate
            FROM admission.application_periods
        ')->aggregate;

        $this->assertSame(0, $count);
    }

    #[Test]
    public function wrong_school_context_hides_admission_applications(): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            $this->markTestSkipped('PostgreSQL required for RLS validation.');
        }

        DB::statement("SELECT set_config('app.current_school_id', '999999', false)");

        $count = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM admission.applications')->aggregate;
        $this->assertSame(0, $count);

        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        $cleared = (int) DB::selectOne('SELECT COUNT(*) AS aggregate FROM admission.applications')->aggregate;
        $this->assertSame(0, $cleared);
    }
}
