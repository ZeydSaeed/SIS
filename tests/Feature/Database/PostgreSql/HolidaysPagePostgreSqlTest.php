<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Attendance\EloquentAttendanceSheetRepository;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «الاجازات» on PostgreSQL: the page lists the school's and the global holidays, a school adds / edits its own
 * holidays inside the academic year, takes them out of the calendar and back (never deleted), global holidays are
 * read-only, other schools' holidays are invisible, and attendance stops treating a removed holiday as a day off.
 */
final class HolidaysPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $key = 0;

    #[Test]
    public function school_manages_its_holidays_and_global_ones_stay_read_only(): void
    {
        $schoolId = $this->createSchool('SCH-HOL-W', 'Holidays Web');
        $otherSchoolId = $this->createSchool('SCH-HOL-W2', 'Holidays Other');
        $yearId = $this->createAcademicYear('AY-HOL-W');
        $table = SchemaHelper::qualified('academic', 'holidays');
        $globalId = (int) DB::table($table)->insertGetId(['academic_year_id' => $yearId, 'school_id' => null, 'name' => 'اليوم الوطني', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'holiday_type' => 1, 'created_at' => now()]);
        $foreignId = (int) DB::table($table)->insertGetId(['academic_year_id' => $yearId, 'school_id' => $otherSchoolId, 'name' => 'عطلة مدرسة أخرى', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'holiday_type' => 2, 'created_at' => now()]);

        $user = $this->actingAsEnrollmentManagerForSchool($schoolId);
        app(SecurityPermissionSeeder::class)->grantSchoolManager($user, $schoolId);

        // The page: the global holiday only (the other school's is invisible).
        $this->get('/holidays?year='.$yearId)->assertOk()->assertInertia(fn ($page) => $page
            ->component('holidays/index')
            ->where('authorization.can_manage', true)
            ->has('holidays', 1)
            ->where('holidays.0.id', $globalId)
            ->where('holidays.0.global', true)
            ->etc());

        // Create: inside the year, a valid type and range.
        $this->write('/holidays', ['academic_year_id' => $yearId, 'name' => 'عطلة الشتاء', 'start_date' => '2026-12-20', 'end_date' => '2027-01-05', 'holiday_type' => 3])
            ->assertSessionHas('success', 'flash.holidays.created');
        $holiday = DB::table($table)->where('school_id', $schoolId)->first();
        $this->assertNotNull($holiday);
        $this->assertSame(1, (int) $holiday->status);

        $this->write('/holidays', ['academic_year_id' => $yearId, 'name' => 'x', 'start_date' => '2027-07-01', 'end_date' => '2027-07-02', 'holiday_type' => 2])
            ->assertSessionHasErrors(['holiday' => 'academic.holiday_outside_year']);
        $this->write('/holidays', ['academic_year_id' => $yearId, 'name' => 'x', 'start_date' => '2026-12-10', 'end_date' => '2026-12-01', 'holiday_type' => 2])
            ->assertSessionHasErrors('end_date');
        $this->write('/holidays', ['academic_year_id' => $yearId, 'name' => 'عطلة الشتاء', 'start_date' => '2026-12-25', 'end_date' => '2026-12-26', 'holiday_type' => 3])
            ->assertSessionHasErrors(['holiday' => 'academic.holiday_duplicate']);
        $this->write('/holidays', ['academic_year_id' => $yearId, 'school_id' => $otherSchoolId, 'name' => 'y', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'holiday_type' => 2])
            ->assertSessionHasErrors('school_id');

        // Edit its own; the global and a foreign holiday cannot be edited from here.
        $this->patchWrite('/holidays/'.$holiday->id, ['name' => 'عطلة نهاية الفصل', 'start_date' => '2026-12-21', 'end_date' => '2027-01-04', 'holiday_type' => 3])
            ->assertSessionHas('success', 'flash.holidays.updated');
        $this->assertSame('عطلة نهاية الفصل', DB::table($table)->where('id', $holiday->id)->value('name'));
        $this->patchWrite('/holidays/'.$globalId, ['name' => 'z', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'holiday_type' => 1])
            ->assertSessionHasErrors(['holiday' => 'academic.holiday_global_readonly']);
        $this->write('/holidays/'.$foreignId.'/status', ['active' => 0])->assertSessionHasErrors(['holiday' => 'academic.holiday_not_found']);
        $this->assertSame(1, (int) DB::table($table)->where('id', $foreignId)->value('status'));

        // Out of the calendar and back: the row stays, the state filter and counters follow.
        $this->write('/holidays/'.$holiday->id.'/status', ['active' => 0])->assertSessionHas('success', 'flash.holidays.deactivated');
        $this->assertSame(2, (int) DB::table($table)->where('id', $holiday->id)->value('status'));
        $this->get('/holidays?year='.$yearId.'&state=2')->assertInertia(fn ($page) => $page
            ->has('holidays', 1)->where('holidays.0.id', (int) $holiday->id)->where('stats.total', 2)->where('stats.active', 1)->etc());
        $this->write('/holidays/'.$holiday->id.'/status', ['active' => 1])->assertSessionHas('success', 'flash.holidays.reactivated');
        $this->assertSame(1, (int) DB::table($table)->where('id', $holiday->id)->value('status'));
        $this->assertDatabaseCount($table, 3);
    }

    #[Test]
    public function viewer_without_manage_rights_cannot_write(): void
    {
        $schoolId = $this->createSchool('SCH-HOL-V', 'Holidays Viewer');
        $yearId = $this->createAcademicYear('AY-HOL-V');
        $this->actingAsStudentViewerWeb(null, $schoolId);

        $this->write('/holidays', ['academic_year_id' => $yearId, 'name' => 'x', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'holiday_type' => 2])
            ->assertForbidden();
    }

    #[Test]
    public function removed_holiday_no_longer_closes_the_attendance_day(): void
    {
        $schoolId = $this->createSchool('SCH-HOL-A', 'Holidays Attendance');
        $yearId = $this->createAcademicYear('AY-HOL-A');
        $table = SchemaHelper::qualified('academic', 'holidays');
        $id = (int) DB::table($table)->insertGetId(['academic_year_id' => $yearId, 'school_id' => $schoolId, 'name' => 'عطلة', 'start_date' => '2026-10-16', 'end_date' => '2026-10-16', 'holiday_type' => 1, 'created_at' => now()]);
        $repository = app(EloquentAttendanceSheetRepository::class);

        $this->assertTrue($repository->isHoliday($schoolId, $yearId, '2026-10-16'));
        DB::table($table)->where('id', $id)->update(['status' => 2]);
        $this->assertFalse($repository->isHoliday($schoolId, $yearId, '2026-10-16'));
    }

    private function write(string $url, array $data): TestResponse
    {
        return $this->from('/holidays')->withHeader('X-Idempotency-Key', 'hol-'.(++$this->key))->post($url, $data);
    }

    private function patchWrite(string $url, array $data): TestResponse
    {
        return $this->from('/holidays')->withHeader('X-Idempotency-Key', 'hol-'.(++$this->key))->patch($url, $data);
    }
}
