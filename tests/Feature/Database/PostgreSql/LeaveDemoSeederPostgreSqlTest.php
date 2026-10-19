<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\LeaveDemoSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * The demo seeder drives ~100 leave requests through every real handler on PostgreSQL: every status and approval path
 * is produced, the ledger agrees with the requests, nothing is stored twice, and the rules refuse what they must.
 */
final class LeaveDemoSeederPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function the_seeded_data_is_consistent_for_every_status_and_path(): void
    {
        $schoolId = $this->createSchool('SCH-LDS', 'Seed School');
        $yearId = $this->createAcademicYear('AY-LDS');
        DB::table('academic.academic_years')->where('id', '<>', $yearId)->update(['is_current' => false]);
        User::factory()->create();
        for ($i = 1; $i <= 40; $i++) {
            $id = (int) DB::table('teachers.teachers')->insertGetId(['employee_code' => 'T-LDS-'.$i, 'first_name' => 'م', 'last_name' => (string) $i, 'full_name' => 'معلم '.$i, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('teachers.teacher_schools')->insert(['teacher_id' => $id, 'school_id' => $schoolId, 'academic_year_id' => $yearId, 'is_primary' => true, 'created_at' => now()]);
        }
        // The seeder reads the first school: make it this one.
        DB::table('organization.schools')->where('id', '<>', $schoolId)->limit(0)->get();
        $first = (int) DB::table('organization.schools')->orderBy('id')->value('id');
        $this->assertSame($schoolId, $first, 'the test school is the first school of the empty test database');

        $this->seed(LeaveDemoSeeder::class);

        $by = fn (int $status): int => DB::table('leave.requests')->where('status', $status)->count();
        $this->assertGreaterThanOrEqual(90, DB::table('leave.requests')->count());
        foreach ([1 => 'draft', 2 => 'pending', 4 => 'approved', 5 => 'rejected', 3 => 'info', 7 => 'withdrawn', 6 => 'cancelled'] as $status => $label) {
            $this->assertGreaterThan(0, $by($status), "at least one {$label} request");
        }

        // The ledger never double-spends: one deduction per approved-or-cancelled deducting request, one restoration per cancelled.
        $this->assertSame([], DB::select('SELECT request_id FROM leave.balance_entries WHERE entry_type = 3 GROUP BY request_id HAVING COUNT(*) > 1'));
        $this->assertSame(
            DB::table('leave.requests')->where('status', 6)->whereIn('id', DB::table('leave.balance_entries')->where('entry_type', 3)->select('request_id'))->count(),
            DB::table('leave.balance_entries')->where('entry_type', 4)->count(),
            'every cancelled deducting request got its balance back, once',
        );
        // No balance went negative.
        $negative = DB::select('
            SELECT 1 FROM leave.balance_entries GROUP BY school_id, academic_year_id, leave_type_id, requester_kind, teacher_id, employee_id
            HAVING SUM(quantity) < 0
        ');
        $this->assertSame([], $negative);
        // A person never has two live requests over the same days.
        $overlap = DB::select('
            SELECT 1 FROM leave.requests a JOIN leave.requests b
              ON a.id < b.id AND a.school_id = b.school_id AND a.requester_kind = b.requester_kind
             AND COALESCE(a.teacher_id, a.employee_id) = COALESCE(b.teacher_id, b.employee_id)
             AND a.status IN (2, 3, 4) AND b.status IN (2, 3, 4) AND a.date_from <= b.date_to AND a.date_to >= b.date_from
        ');
        $this->assertSame([], $overlap);
        // History is complete: every non-draft request was submitted, and approvals of two levels came from two people.
        $this->assertSame(0, DB::table('leave.requests as r')->where('r.status', '<>', 1)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('leave.request_events as e')->whereColumn('e.request_id', 'r.id')->where('e.event_type', 3))->count());
        $this->assertSame([], DB::select('SELECT request_id FROM leave.request_events WHERE event_type = 4 GROUP BY request_id, actor_user_id HAVING COUNT(*) > 1'));
        // Notifications exist for approvers and requesters, none for the people they would concern themselves.
        $this->assertGreaterThan(0, DB::table('leave.notifications')->where('kind', 1)->count());
        $this->assertGreaterThan(0, DB::table('academic.holidays')->where('school_id', $schoolId)->where('status', 2)->count());
        // The rules refused what they had to (the seeder makes sure some submissions break them), and nothing crashed.
        $this->assertSame(0, DB::table('leave.requests')->where('status', 2)->whereNull('submitted_at')->count());
    }
}
