<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTtPeriodShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_timetable_period(): void
    {
        $schoolId = $this->createSchool('SCH-TT-PS', 'Period Show');
        $this->actingAsTimetableManagerForSchool($schoolId);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $periodId = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolId,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:45:00',
            'period_type' => 1,
        ]);

        $this->getJson('/api/v1/timetable/periods/'.$periodId)
            ->assertOk()
            ->assertJsonPath('data.id', $periodId)
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.period_number', 1);
    }
}
