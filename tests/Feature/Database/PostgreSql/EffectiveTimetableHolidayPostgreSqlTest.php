<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Timetable\Queries\GetEffectiveTimetableHandler;
use App\Application\Timetable\Queries\GetEffectiveTimetableQuery;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** The effective timetable tells its readers when a date is closed by a holiday in force (global or the school's own). */
final class EffectiveTimetableHolidayPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function the_effective_timetable_flags_holiday_dates_only_for_active_holidays_in_force(): void
    {
        $tag = substr(uniqid(), -6);
        $school = $this->createSchool('SCH-EH-'.$tag, 'Holiday School');
        $other = $this->createSchool('SCH-EH2-'.$tag, 'Other School');
        $year = $this->createAcademicYear('AY-EH-'.$tag);
        $row = ['academic_year_id' => $year, 'holiday_type' => 1, 'created_at' => now()];
        DB::table('academic.holidays')->insert([
            $row + ['school_id' => null, 'name' => 'عامة', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'status' => 1],
            $row + ['school_id' => $school, 'name' => 'مدرسية', 'start_date' => '2026-11-10', 'end_date' => '2026-11-11', 'status' => 1],
            $row + ['school_id' => $school, 'name' => 'خارج التقويم', 'start_date' => '2026-11-17', 'end_date' => '2026-11-17', 'status' => 2],
        ]);

        $handler = app(GetEffectiveTimetableHandler::class);
        $flag = fn (int $schoolId, string $date): bool => $handler->handle(new GetEffectiveTimetableQuery($schoolId, $year, $date, null, null))->meta['holiday'];

        $this->assertTrue($flag($school, '2026-11-03'), 'a global holiday closes every school');
        $this->assertTrue($flag($other, '2026-11-03'));
        $this->assertTrue($flag($school, '2026-11-11'), 'the school holiday covers its whole range');
        $this->assertFalse($flag($other, '2026-11-11'), 'another school is not closed by it');
        $this->assertFalse($flag($school, '2026-11-17'), 'a holiday taken out of the calendar does not close the day');
        $this->assertFalse($flag($school, '2026-11-04'));
    }
}
