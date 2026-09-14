<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTtPeriodsIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_timetable_periods(): void
    {
        $schoolId = $this->createSchool('SCH-TT-PL', 'Period List');
        $this->actingAsTimetableManagerForSchool($schoolId);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $periodId = (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId([
            'school_id' => $schoolId,
            'period_number' => 2,
            'start_time' => '09:00:00',
            'end_time' => '09:45:00',
            'period_type' => 1,
        ]);

        $this->getJson('/api/v1/timetable/periods')
            ->assertOk()
            ->assertJsonPath('data.0.id', $periodId)
            ->assertJsonPath('data.0.period_number', 2);
    }
}
