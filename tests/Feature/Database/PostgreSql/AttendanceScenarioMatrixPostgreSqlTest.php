<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Shared\Exceptions\SisDomainException;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsAttendanceLessonGraph;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * «إدارة الحضور والغياب» — scenario matrix (2026-10-13).
 *
 * 20 different teaching units (7 classes × sections A / B / C, 4 branches, 8 departments, theory and practical
 * subjects, 8 teachers incl. co-teachers) over a school week (5 days × up to 6 periods). Every lesson runs a
 * randomised (seeded, reproducible) workflow through the real HTTP routes — open, drafts, stale versions,
 * incomplete / complete submit, start review, approve / return / reject, resubmission, bulk approve with stale
 * items, official corrections, excuses, foreign teachers / roles / schools — followed by a fuzz phase of malformed
 * payloads. After every request:
 *  - no 5xx;
 *  - a refused action carries a known error code that has an Arabic message (the error dialog text);
 *  - the database invariants hold (sheet lifecycle edges, event chain, versions, completeness, excuse links).
 * A summary (operations per kind, codes met) is written to the system temp dir for the change report.
 */
#[Group('scenario')]
final class AttendanceScenarioMatrixPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsAttendanceLessonGraph;

    private const DAYS = ['2026-10-11' => 1, '2026-10-12' => 2, '2026-10-13' => 3, '2026-10-14' => 4, '2026-10-15' => 5];

    private const PERIODS = [['08:00', '08:45'], ['08:50', '09:35'], ['09:40', '10:25'], ['10:30', '11:15'], ['11:20', '12:05'], ['12:10', '12:55']];

    /** Allowed sheet status edges (from → to) of AttendanceSheetWorkflow plus the opening event. */
    private const EDGES = ['0>1', '1>1', '5>5', '1>2', '5>2', '2>3', '2>4', '3>4', '2>5', '3>5', '2>6', '3>6', '4>4', '2>2', '3>3'];

    private int $schoolId;

    private int $yearId;

    /** @var array<string, string> error code => Arabic text (resources/js/i18n/ar.ts) */
    private array $codes = [];

    /** @var array<string, int> */
    private array $ops = [];

    /** @var array<string, int> */
    private array $codesMet = [];

    /** @var list<string> */
    private array $violations = [];

    private User $manager;

    private User $viewer;

    private User $foreignManager;

    private int $foreignSchoolId;

    /** The school header of the acting user (kept across requests; per-request headers are dropped). */
    private int $actingSchoolId = 0;

    /** @var list<array{teacher_id: int, user: User}> */
    private array $teachers = [];

    /** @var list<array<string, mixed>> */
    private array $units = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Reproducible: SIS_ATTENDANCE_SCENARIO_SEED replays another random walk over the same 20 sections.
        mt_srand((int) (getenv('SIS_ATTENDANCE_SCENARIO_SEED') ?: 20261013));
        $this->codes = $this->arabicErrorCodes();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function twenty_sections_run_every_workflow_path_without_errors_or_broken_invariants(): void
    {
        $this->buildMatrix();

        foreach (self::DAYS as $date => $weekday) {
            foreach ($this->units as $unit) {
                foreach ($unit['lessons'] as $lesson) {
                    if ($lesson['weekday'] === $weekday) {
                        $this->runLesson($unit, $lesson, $date);
                    }
                }
            }
            $this->bulkApproveDay($date);
            $this->excusesForDay($date);
            $this->staffForDay($date);
            $this->exceptionalForDay($date);
            $this->readEveryPage($date);
        }
        $this->fuzz(1200);
        $this->crossChecks();

        $summary = [
            'requests' => array_sum($this->ops),
            'operations' => $this->ops,
            'codes_met' => $this->codesMet,
            'sessions' => DB::table('attendance.sessions')->where('school_id', $this->schoolId)->count(),
            'records' => DB::table('attendance.records')->where('school_id', $this->schoolId)->count(),
            'events' => DB::table('attendance.sheet_events')->where('school_id', $this->schoolId)->count(),
            'sheet_statuses' => DB::table('attendance.sessions')->where('school_id', $this->schoolId)->selectRaw('sheet_status, count(*) n')->groupBy('sheet_status')->pluck('n', 'sheet_status')->all(),
            'violations' => $this->violations,
        ];
        file_put_contents(sys_get_temp_dir().DIRECTORY_SEPARATOR.'sis-attendance-scenario.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->assertGreaterThan(2500, $summary['requests']);
        $this->assertSame([], $this->violations, implode("\n", array_slice($this->violations, 0, 40)));
    }

    // ── Fixture: 20 teaching units ──────────────────────────────────────────────────────────────────────────

    private function buildMatrix(): void
    {
        // Role assignments start «today»: build the school before the first lesson day.
        Carbon::setTestNow('2026-10-01 06:00:00');
        $this->schoolId = $this->createSchool('SCH-MX1', 'Attendance matrix');
        $foreignSchool = $this->createSchool('SCH-MX2', 'Attendance matrix other');
        $this->foreignSchoolId = $foreignSchool;
        $this->yearId = $this->createAcademicYear('AY-MX');
        DB::table('academic.academic_years')->where('id', '<>', $this->yearId)->update(['is_current' => false]);
        DB::table('academic.academic_years')->where('id', $this->yearId)->update(['is_current' => true]);

        $this->manager = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($this->manager, $this->schoolId);
        $this->viewer = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceViewer($this->viewer, $this->schoolId);
        $this->foreignManager = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($this->foreignManager, $foreignSchool);

        $branches = [];
        $departments = [];
        foreach (['الصناعي', 'الزراعي', 'التجاري', 'الحاسوب'] as $b => $branchName) {
            $branches[$b] = (int) DB::table('organization.branches')->insertGetId(['school_id' => $this->schoolId, 'code' => "MXB{$b}", 'name' => $branchName, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([1, 2] as $d) {
                $departments[] = ['branch_id' => $branches[$b], 'id' => (int) DB::table('organization.departments')->insertGetId([
                    'school_id' => $this->schoolId, 'branch_id' => $branches[$b], 'code' => "MXD{$b}{$d}", 'name' => "{$branchName} اختصاص {$d}", 'department_type' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                ])];
            }
        }

        $subjects = [];
        foreach (['رياضيات' => 1, 'فيزياء' => 1, 'لغة عربية' => 1, 'ورشة كهرباء' => 2, 'مختبر حاسوب' => 2, 'تدريب زراعي' => 2] as $name => $type) {
            $subjects[] = (int) DB::table('curriculum.subjects')->insertGetId(['code' => 'MX'.substr(md5($name), 0, 6), 'name' => $name, 'subject_type' => $type, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Roles are granted before the first request (permissions are cached per user).
        for ($i = 0; $i < 8; $i++) {
            $teacher = $this->seedTeacher($this->schoolId, $this->yearId);
            app(SecurityPermissionSeeder::class)->grantAttendanceTeacher($teacher['user'], $this->schoolId);
            $this->teachers[] = $teacher;
        }
        $periods = array_map(fn (array $p, int $n): int => (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $this->schoolId, 'period_number' => 700 + $n, 'start_time' => $p[0].':00', 'end_time' => $p[1].':00', 'period_type' => 1,
        ]), self::PERIODS, array_keys(self::PERIODS));

        // 7 classes × A / B / C = 21 sections; the first 20 are the matrix units.
        $unit = 0;
        foreach (['الأول', 'الثاني', 'الثالث', 'الأول مسائي', 'الثاني مسائي', 'الثالث مسائي', 'الرابع'] as $c => $className) {
            $classId = (int) $this->createClassForSchool($this->schoolId, $this->yearId)->id;
            DB::table('enrollment.classes')->where('id', $classId)->update(['code' => "CLS-MX{$c}", 'name' => $className]);
            foreach (['A', 'B', 'C'] as $letter) {
                if ($unit >= 20) {
                    break 2;
                }
                $sectionId = (int) DB::table('enrollment.sections')->insertGetId(['class_id' => $classId, 'code' => "SEC-{$letter}", 'name' => $letter, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $department = $departments[$unit % count($departments)];
                $enrollments = [];
                $students = [];
                for ($s = 1, $n = 3 + ($unit % 4); $s <= $n; $s++) {
                    $student = $this->createStudentForSchool($this->schoolId, ['full_name' => "طالب {$unit}-{$s}", 'first_name' => "طالب{$s}"]);
                    $students[] = (int) $student->id;
                    $enrollments[] = (int) DB::table('enrollment.enrollments')->insertGetId([
                        'student_id' => $student->id, 'academic_year_id' => $this->yearId, 'school_id' => $this->schoolId, 'class_id' => $classId, 'section_id' => $sectionId,
                        'branch_id' => $department['branch_id'], 'department_id' => $department['id'], 'status' => 1, 'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $lessons = [];
                foreach (self::DAYS as $weekday) {
                    if (($unit + $weekday) % 3 === 0) {
                        continue; // not every unit has a lesson every day
                    }
                    $period = ($unit + $weekday) % count($periods);
                    $teacher = $this->teachers[($unit + $weekday) % count($this->teachers)];
                    $co = $unit % 5 === 0 ? $this->teachers[($unit + $weekday + 3) % count($this->teachers)] : null;
                    $scheduleId = $this->seedSchedule($this->schoolId, $this->yearId, $sectionId, $subjects[($unit + $weekday) % count($subjects)], $teacher['teacher_id'], $periods[$period], $weekday);
                    if ($co !== null && $co['teacher_id'] !== $teacher['teacher_id']) {
                        DB::table('timetable.schedules')->where('id', $scheduleId)->update(['co_teacher_id' => $co['teacher_id']]);
                    }
                    $lessons[] = ['schedule_id' => $scheduleId, 'weekday' => $weekday, 'period' => $period, 'teacher' => $teacher, 'co' => $co !== null && $co['teacher_id'] !== $teacher['teacher_id'] ? $co : null];
                }
                $this->units[] = ['index' => $unit, 'section_id' => $sectionId, 'class_id' => $classId, 'enrollments' => $enrollments, 'students' => $students, 'lessons' => $lessons];
                $unit++;
            }
        }
        $this->assertCount(20, $this->units);
        $this->assertSame(20, DB::table('enrollment.sections as s')->join('enrollment.classes as c', 'c.id', '=', 's.class_id')->where('c.school_id', $this->schoolId)->whereIn('s.name', ['A', 'B', 'C'])->count());
        // A holiday on Tuesday afternoon? No — a full holiday for one unit-free date keeps the holiday path covered.
        DB::table('academic.holidays')->insert(['academic_year_id' => $this->yearId, 'school_id' => $this->schoolId, 'name' => 'عطلة', 'start_date' => '2026-10-16', 'end_date' => '2026-10-16', 'holiday_type' => 1, 'created_at' => now()]);
    }

    // ── One lesson: a randomised walk through the sheet lifecycle ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $unit
     * @param  array<string, mixed>  $lesson
     */
    private function runLesson(array $unit, array $lesson, string $date): void
    {
        $this->atLesson($date, $lesson['period']);
        $teacher = mt_rand(0, 3) === 0 && $lesson['co'] !== null ? $lesson['co'] : $lesson['teacher'];

        // A teacher of another lesson cannot open this one.
        $other = $this->otherTeacher($lesson);
        if ($other !== null && mt_rand(0, 2) === 0) {
            $this->actAs($other['user'], 'teacher');
            $this->hit('open.foreign', 'post', '/attendance/lessons/open', ['schedule_id' => $lesson['schedule_id'], 'date' => $date], 'f-'.uniqid());
        }

        $this->actAs($teacher['user'], 'teacher');
        $openKey = 'o-'.uniqid();
        $this->hit('open', 'post', '/attendance/lessons/open', ['schedule_id' => $lesson['schedule_id'], 'date' => $date], $openKey);
        // Repeated open (same key, then a new key) never creates a second sheet.
        $this->hit('open.repeat', 'post', '/attendance/lessons/open', ['schedule_id' => $lesson['schedule_id'], 'date' => $date], mt_rand(0, 1) ? $openKey : 'o2-'.uniqid());
        $session = DB::table('attendance.sessions')->where('schedule_id', $lesson['schedule_id'])->where('session_date', $date)->first();
        if ($session === null) {
            $this->violations[] = "no sheet for schedule {$lesson['schedule_id']} on {$date}";

            return;
        }
        $id = (int) $session->id;
        $this->hit('sheet.show', 'get', "/attendance/sheets/{$id}");
        $this->hit('sheet.student_record', 'get', "/attendance/sheets/{$id}?student={$unit['students'][0]}", headers: ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'attendance/sheet', 'X-Inertia-Partial-Data' => 'student_record']);

        // Draft with some students unmarked, then a stale version and a foreign enrollment (refused).
        $statuses = array_map(fn (): int => [0, 1, 1, 1, 2, 3, 5][mt_rand(0, 6)], $unit['enrollments']);
        $this->hit('draft', 'post', "/attendance/sheets/{$id}/draft", $this->marks($this->version($id), $unit['enrollments'], $statuses), 'd-'.uniqid());
        if (mt_rand(0, 2) === 0) {
            $this->hit('draft.stale', 'post', "/attendance/sheets/{$id}/draft", $this->marks(max(0, $this->version($id) - 1), $unit['enrollments'], $statuses), 'ds-'.uniqid());
        }
        if (mt_rand(0, 3) === 0) {
            $foreign = $this->units[($unit['index'] + 1) % 20]['enrollments'][0];
            $this->hit('draft.foreign_student', 'post', "/attendance/sheets/{$id}/draft", $this->marks($this->version($id), [$foreign], [1]), 'dx-'.uniqid());
        }
        if (mt_rand(0, 3) === 0) {
            $this->hit('draft.excused_by_teacher', 'post', "/attendance/sheets/{$id}/draft", $this->marks($this->version($id), [$unit['enrollments'][0]], [4]), 'de-'.uniqid());
        }
        if (in_array(0, $statuses, true)) {
            $this->hit('submit.incomplete', 'post', "/attendance/sheets/{$id}/submit", $this->marks($this->version($id), $unit['enrollments'], $statuses, true), 'si-'.uniqid());
        }
        $complete = array_map(fn (int $s): int => $s === 0 ? [1, 2, 3][mt_rand(0, 2)] : $s, $statuses);
        if (mt_rand(0, 4) === 0) {
            $this->hit('submit.unconfirmed', 'post', "/attendance/sheets/{$id}/submit", $this->marks($this->version($id), $unit['enrollments'], $complete), 'su-'.uniqid());
        }
        $submitKey = 's-'.uniqid();
        $this->hit('submit', 'post', "/attendance/sheets/{$id}/submit", $this->marks($this->version($id), $unit['enrollments'], $complete, true), $submitKey);
        // A double click (same key) replays; editing a submitted sheet is refused.
        $this->hit('submit.replay', 'post', "/attendance/sheets/{$id}/submit", $this->marks($this->version($id) - 1, $unit['enrollments'], $complete, true), $submitKey);
        $this->hit('draft.after_submit', 'post', "/attendance/sheets/{$id}/draft", $this->marks($this->version($id), $unit['enrollments'], $complete), 'da-'.uniqid());

        // Roles that may not review.
        $this->actAs($teacher['user'], 'teacher');
        $this->hit('review.by_teacher', 'post', "/attendance/sheets/{$id}/review", ['action' => 'approve', 'version' => $this->version($id)], 'rt-'.uniqid());
        $this->actAs($this->viewer, 'viewer');
        $this->hit('review.by_viewer', 'post', "/attendance/sheets/{$id}/review", ['action' => 'approve', 'version' => $this->version($id)], 'rv-'.uniqid());
        $this->actAs($this->foreignManager, 'foreign');
        $this->hit('sheet.foreign_school', 'get', "/attendance/sheets/{$id}");

        // Review: a random path; some sheets stay «submitted» for the day's bulk approve.
        $this->actAs($this->manager, 'manager');
        $path = mt_rand(0, 9);
        if ($path <= 1) {
            return; // left for bulk approve
        }
        if ($path === 2) {
            $this->hit('review.start', 'post', "/attendance/sheets/{$id}/review", ['action' => 'start_review', 'version' => $this->version($id)], 'rs-'.uniqid());
        }
        if ($path <= 5) {
            $this->hit('review.approve', 'post', "/attendance/sheets/{$id}/review", ['action' => 'approve', 'version' => $this->version($id), 'reason' => mt_rand(0, 1) ? 'تمت المراجعة' : null], 'ra-'.uniqid());
            $this->hit('review.approve_again', 'post', "/attendance/sheets/{$id}/review", ['action' => 'approve', 'version' => $this->version($id)], 'ra2-'.uniqid());
            $this->correct($unit, $id);

            return;
        }
        if ($path === 6) {
            $this->hit('review.reject_no_reason', 'post', "/attendance/sheets/{$id}/review", ['action' => 'reject', 'version' => $this->version($id)], 'rj0-'.uniqid());
            $this->hit('review.reject', 'post', "/attendance/sheets/{$id}/review", ['action' => 'reject', 'version' => $this->version($id), 'reason' => 'الحصة لم تُعقد'], 'rj-'.uniqid());

            return;
        }
        // Return → the teacher corrects and resubmits → approve.
        $this->hit('review.return_no_reason', 'post', "/attendance/sheets/{$id}/review", ['action' => 'return', 'version' => $this->version($id)], 'rr0-'.uniqid());
        $this->hit('review.return', 'post', "/attendance/sheets/{$id}/review", ['action' => 'return', 'version' => $this->version($id), 'reason' => 'راجع حالة الطالب الأول'], 'rr-'.uniqid());
        $this->actAs($teacher['user'], 'teacher');
        $complete[0] = $complete[0] === 1 ? 2 : 1;
        $this->hit('draft.returned', 'post', "/attendance/sheets/{$id}/draft", $this->marks($this->version($id), $unit['enrollments'], $complete), 'dr-'.uniqid());
        $this->hit('submit.resubmit', 'post', "/attendance/sheets/{$id}/submit", $this->marks($this->version($id), $unit['enrollments'], $complete, true), 'sr-'.uniqid());
        $this->actAs($this->manager, 'manager');
        $this->hit('review.approve_resubmitted', 'post', "/attendance/sheets/{$id}/review", ['action' => 'approve', 'version' => $this->version($id)], 'rar-'.uniqid());
    }

    /** Official correction of an approved sheet: refused without a reason, applied with one. */
    private function correct(array $unit, int $id): void
    {
        if (mt_rand(0, 2) !== 0) {
            return;
        }
        $student = $unit['students'][mt_rand(0, count($unit['students']) - 1)];
        $this->hit('correct.no_reason', 'post', "/attendance/sheets/{$id}/students/{$student}/correct", ['academic_year_id' => $this->yearId, 'new_status' => 2], 'c0-'.uniqid());
        $this->hit('correct', 'post', "/attendance/sheets/{$id}/students/{$student}/correct", ['academic_year_id' => $this->yearId, 'new_status' => [1, 2, 3, 5][mt_rand(0, 3)], 'reason' => 'تصحيح بعد مراجعة ولي الأمر'], 'c-'.uniqid());
    }

    /** End of day: bulk approve every submitted sheet, one of them with a stale version. */
    private function bulkApproveDay(string $date): void
    {
        $this->actAs($this->manager, 'manager');
        $sheets = DB::table('attendance.sessions')->where('school_id', $this->schoolId)->where('session_date', $date)->whereIn('sheet_status', [2, 3])->get(['id', 'version']);
        if ($sheets->isEmpty()) {
            return;
        }
        $items = $sheets->map(fn (object $s, int $i): array => ['session_id' => (int) $s->id, 'version' => $i === 0 ? (int) $s->version + 7 : (int) $s->version])->all();
        $this->hit('bulk_approve', 'post', '/attendance/review/bulk-approve', ['items' => $items], 'b-'.uniqid());
        $this->hit('bulk_approve.empty', 'post', '/attendance/review/bulk-approve', ['items' => []], 'b0-'.uniqid());
        if ((int) DB::table('attendance.sessions')->where('id', $sheets[0]->id)->value('sheet_status') === 4) {
            $this->violations[] = "bulk approve accepted a stale version for sheet {$sheets[0]->id}";
        }
    }

    /** Excuses: one per unit-day for an absent student — approve or reject; plus refused shapes. */
    private function excusesForDay(string $date): void
    {
        $this->actAs($this->manager, 'manager');
        $absent = DB::table('attendance.records as r')->join('attendance.sessions as s', 's.id', '=', 'r.session_id')
            ->where('r.school_id', $this->schoolId)->where('r.attendance_date', $date)->where('r.status', 2)->whereIn('s.sheet_status', [2, 3, 4])
            ->select('r.enrollment_id', 'r.student_id')->distinct()->limit(6)->get();
        foreach ($absent as $i => $row) {
            $this->hit('excuse.submit', 'post', '/attendance/excuses', ['enrollment_id' => $row->enrollment_id, 'date_from' => $date, 'date_to' => $date, 'excuse_type' => [1, 2, 3, 4, 9][$i % 5], 'reason' => 'ظرف صحي موثق'], 'e-'.uniqid());
            $excuse = DB::table('attendance.excuses')->where('enrollment_id', $row->enrollment_id)->where('date_from', $date)->orderByDesc('id')->first();
            if ($excuse === null) {
                continue;
            }
            if ($i % 3 === 2) {
                $this->hit('excuse.reject_no_note', 'post', "/attendance/excuses/{$excuse->id}/decide", ['decision' => 'reject', 'version' => $excuse->version], 'er0-'.uniqid());
                $this->hit('excuse.reject', 'post', "/attendance/excuses/{$excuse->id}/decide", ['decision' => 'reject', 'version' => $excuse->version, 'note' => 'المستند غير كافٍ'], 'er-'.uniqid());
            } else {
                $this->hit('excuse.approve', 'post', "/attendance/excuses/{$excuse->id}/decide", ['decision' => 'approve', 'version' => $excuse->version], 'ea-'.uniqid());
                $this->hit('excuse.approve_again', 'post', "/attendance/excuses/{$excuse->id}/decide", ['decision' => 'approve', 'version' => $excuse->version], 'ea2-'.uniqid());
            }
        }
        $this->hit('excuse.reversed_dates', 'post', '/attendance/excuses', ['enrollment_id' => $this->units[0]['enrollments'][0], 'date_from' => $date, 'date_to' => '2026-09-01', 'excuse_type' => 1, 'reason' => 'تواريخ معكوسة'], 'ex-'.uniqid());
        $this->hit('excuse.outside_year', 'post', '/attendance/excuses', ['enrollment_id' => $this->units[0]['enrollments'][0], 'date_from' => '2025-01-01', 'date_to' => '2025-01-02', 'excuse_type' => 1, 'reason' => 'خارج السنة'], 'ey-'.uniqid());
    }

    /** Teachers' work attendance: rotating code → check-in / check-out; manager records and corrections. */
    private function staffForDay(string $date): void
    {
        Carbon::setTestNow(Carbon::parse($date.' 04:40:00', 'UTC')); // 07:40 school time
        $this->actAs($this->manager, 'manager');
        $code = (string) ($this->hit('staff.code', 'post', '/attendance/staff/codes', [], 'sc-'.uniqid())->getSession()->get('attendanceResult')['code'] ?? '');
        foreach ($this->teachers as $i => $teacher) {
            $row = DB::table('attendance.staff_records')->where('teacher_id', $teacher['teacher_id'])->where('work_date', $date)->first();
            if ($i % 3 === 0) {
                $this->actAs($teacher['user'], 'teacher');
                $this->hit('staff.checkin_wrong', 'post', '/attendance/staff/check-in', ['code' => '0000'], 'cw-'.uniqid());
                $this->hit('staff.checkin', 'post', '/attendance/staff/check-in', ['code' => $code], 'ci-'.uniqid());
                $this->hit('staff.checkout', 'post', '/attendance/staff/check-in', ['code' => $code], 'co-'.uniqid());
                $this->hit('staff.checkin_third', 'post', '/attendance/staff/check-in', ['code' => $code], 'c3-'.uniqid());
                $this->hit('staff.by_teacher', 'post', '/attendance/staff', ['teacher_id' => $teacher['teacher_id'], 'work_date' => $date, 'attendance_status' => 1], 'sbt-'.uniqid());
                $this->actAs($this->manager, 'manager');
            } else {
                $status = [1, 2, 3, 4, 5, 6, 7][mt_rand(0, 6)];
                $this->hit('staff.record', 'post', '/attendance/staff', ['teacher_id' => $teacher['teacher_id'], 'work_date' => $date, 'attendance_status' => $status, 'check_in' => $status === 2 ? null : '07:35', 'minutes_late' => $status === 3 ? 12 : null, 'version' => $row?->version], 'sr-'.uniqid());
            }
            $row = DB::table('attendance.staff_records')->where('teacher_id', $teacher['teacher_id'])->where('work_date', $date)->first();
            if ($row !== null && mt_rand(0, 2) === 0) {
                $this->hit('staff.correct_no_reason', 'post', '/attendance/staff', ['teacher_id' => $teacher['teacher_id'], 'work_date' => $date, 'attendance_status' => 2, 'version' => $row->version], 'scn-'.uniqid());
                $this->hit('staff.correct_stale', 'post', '/attendance/staff', ['teacher_id' => $teacher['teacher_id'], 'work_date' => $date, 'attendance_status' => 2, 'version' => (int) $row->version + 5, 'reason' => 'تصحيح'], 'scs-'.uniqid());
                $this->hit('staff.correct', 'post', '/attendance/staff', ['teacher_id' => $teacher['teacher_id'], 'work_date' => $date, 'attendance_status' => 5, 'version' => $row->version, 'reason' => 'إجازة معتمدة'], 'scc-'.uniqid());
            }
        }
        $this->hit('staff.foreign_teacher', 'post', '/attendance/staff', ['teacher_id' => 999999, 'work_date' => $date, 'attendance_status' => 1], 'sf-'.uniqid());
    }

    /** Off-timetable lessons: opened once with a reason; a duplicate and a foreign subject are refused. */
    private function exceptionalForDay(string $date): void
    {
        $unit = $this->units[mt_rand(0, 19)];
        $teacher = $this->teachers[mt_rand(0, count($this->teachers) - 1)];
        $period = (int) DB::table('timetable.periods')->where('school_id', $this->schoolId)->orderByDesc('period_number')->value('id');
        $subject = (int) DB::table('curriculum.subjects')->where('code', 'like', 'MX%')->orderBy('id')->value('id');
        $payload = ['date' => $date, 'section_id' => $unit['section_id'], 'subject_id' => $subject, 'teacher_id' => $teacher['teacher_id'], 'period_id' => $period];
        $this->actAs($this->manager, 'manager');
        $this->hit('exceptional.no_reason', 'post', '/attendance/lessons/exceptional', $payload, 'xn-'.uniqid());
        $this->hit('exceptional', 'post', '/attendance/lessons/exceptional', $payload + ['reason' => 'حصة تعويضية'], 'x-'.uniqid());
        $this->hit('exceptional.duplicate', 'post', '/attendance/lessons/exceptional', $payload + ['reason' => 'مرة ثانية'], 'xd-'.uniqid());
        $this->hit('exceptional.foreign_subject', 'post', '/attendance/lessons/exceptional', ['subject_id' => 999999] + $payload + ['reason' => 'مادة غير معروفة'], 'xf-'.uniqid());
        $this->hit('exceptional.holiday', 'post', '/attendance/lessons/exceptional', ['date' => '2026-10-16'] + $payload + ['reason' => 'عطلة'], 'xh-'.uniqid());
        $this->actAs($teacher['user'], 'teacher');
        $this->hit('exceptional.by_teacher', 'post', '/attendance/lessons/exceptional', $payload + ['reason' => 'معلم'], 'xt-'.uniqid());
    }

    /** Every attendance page, for each role, with the day's filters (multi-value) — no 5xx. */
    private function readEveryPage(string $date): void
    {
        $unit = $this->units[mt_rand(0, 19)];
        $teacher = $unit['lessons'][0]['teacher'] ?? $this->teachers[0];
        $pages = [
            '/attendance', "/attendance?date_from={$date}&date_to={$date}&sheet_status=", "/attendance/review?date_from={$date}&date_to={$date}",
            "/attendance/review?section_id[]={$unit['section_id']}&class_id[]={$unit['class_id']}&sheet_status[]=2&sheet_status[]=4&per_page=7",
            "/attendance/my?date={$date}", "/attendance/my?date={$date}&teacher_id={$teacher['teacher_id']}", '/attendance/dashboard', '/attendance/dashboard?scope=central',
            '/attendance/excuses?status=', "/attendance/excuses?student=طالب&class_id[]={$unit['class_id']}", "/attendance/staff?date={$date}",
            '/attendance/alerts?status=', '/attendance/settings', '/attendance/my?date=2026-10-16',
        ];
        foreach (['students', 'student_detail', 'sections', 'subjects', 'teachers', 'daily', 'staff', 'lesson_types', 'periods', 'excuses', 'risk'] as $type) {
            $pages[] = "/attendance/reports?type={$type}&date_from=2026-10-11&date_to={$date}&section_id[]={$unit['section_id']}&per_page=9";
        }
        $this->actAs($this->manager, 'manager');
        foreach ($pages as $url) {
            $this->hit('page.manager', 'get', $url);
        }
        $this->actAs($teacher['user'], 'teacher');
        foreach (["/attendance/my?date={$date}", '/attendance', "/attendance/staff?date={$date}", '/attendance/settings', '/attendance/reports'] as $url) {
            $this->hit('page.teacher', 'get', $url);
        }
        $this->actAs($this->viewer, 'viewer');
        foreach (['/attendance/dashboard', '/attendance/reports?type=risk', '/attendance/review'] as $url) {
            $this->hit('page.viewer', 'get', $url);
        }
        $this->actAs($this->manager, 'manager');
        $this->hit('alerts.detect', 'post', '/attendance/alerts/detect', [], 'ad-'.uniqid());
    }

    // ── Fuzz: malformed payloads on every write route ────────────────────────────────────────────────────────

    private function fuzz(int $count): void
    {
        $this->actAs($this->manager, 'manager');
        $sessionIds = DB::table('attendance.sessions')->where('school_id', $this->schoolId)->pluck('id')->all();
        if ($sessionIds === []) {
            $this->violations[] = 'fuzz: no sheet was ever opened';

            return;
        }
        $junk = [null, '', 'x', -1, 0, 999999999, 1.5, true, [], ['a' => 1], str_repeat('ب', 3000), "' OR 1=1 --", '<script>alert(1)</script>', '2026-02-30', '٣'];
        $pick = fn () => $junk[mt_rand(0, count($junk) - 1)];
        for ($i = 0; $i < $count; $i++) {
            $id = $sessionIds[mt_rand(0, count($sessionIds) - 1)];
            $route = mt_rand(0, 10);
            match ($route) {
                0 => $this->hit('fuzz.draft', 'post', "/attendance/sheets/{$id}/draft", ['version' => $pick(), 'marks' => [['enrollment_id' => $pick(), 'status' => $pick(), 'minutes_late' => $pick(), 'note' => $pick()]]], 'z'.$i),
                1 => $this->hit('fuzz.submit', 'post', "/attendance/sheets/{$id}/submit", ['version' => $pick(), 'marks' => $pick(), 'confirm' => $pick()], 'z'.$i),
                2 => $this->hit('fuzz.review', 'post', "/attendance/sheets/{$id}/review", ['action' => $pick(), 'version' => $pick(), 'reason' => $pick()], 'z'.$i),
                3 => $this->hit('fuzz.correct', 'post', "/attendance/sheets/{$id}/students/".mt_rand(1, 999999).'/correct', ['academic_year_id' => $pick(), 'new_status' => $pick(), 'reason' => $pick()], 'z'.$i),
                4 => $this->hit('fuzz.excuse', 'post', '/attendance/excuses', ['enrollment_id' => $pick(), 'date_from' => $pick(), 'date_to' => $pick(), 'excuse_type' => $pick(), 'reason' => $pick()], 'z'.$i),
                5 => $this->hit('fuzz.bulk', 'post', '/attendance/review/bulk-approve', ['items' => [['session_id' => $pick(), 'version' => $pick()]]], 'z'.$i),
                6 => $this->hit('fuzz.staff', 'post', '/attendance/staff', ['teacher_id' => $pick(), 'work_date' => $pick(), 'attendance_status' => $pick(), 'version' => $pick()], 'z'.$i),
                7 => $this->hit('fuzz.exceptional', 'post', '/attendance/lessons/exceptional', ['date' => $pick(), 'section_id' => $pick(), 'subject_id' => $pick(), 'teacher_id' => $pick(), 'period_id' => $pick(), 'reason' => $pick()], 'z'.$i),
                default => $this->hit('fuzz.read', 'get', ['/attendance/reports', '/attendance', '/attendance/review', '/attendance/my', '/attendance/staff', '/attendance/excuses', '/attendance/alerts', '/attendance/dashboard', "/attendance/sheets/{$id}"][mt_rand(0, 8)].'?'.http_build_query([
                    'type' => $pick(), 'section_id' => $pick(), 'teacher_id' => $pick(), 'date' => $pick(), 'date_from' => $pick(), 'date_to' => $pick(), 'per_page' => $pick(), 'page' => $pick(),
                    'q' => $pick(), 'student' => $pick(), 'status' => $pick(), 'sheet_status' => $pick(), 'rule_code' => $pick(), 'scope' => $pick(), 'academic_year_id' => $pick(), 'code' => $pick(), 'via' => $pick(),
                ])),
            };
        }
    }

    // ── Cross checks: report totals = raw records; tenant isolation ───────────────────────────────────────────

    private function crossChecks(): void
    {
        $raw = DB::table('attendance.records as r')->join('attendance.sessions as s', 's.id', '=', 'r.session_id')
            ->where('r.school_id', $this->schoolId)->where('r.status', '>', 0)->where('s.status', '<>', 3)->where('s.sheet_status', '<>', 6)
            ->selectRaw('r.status, count(*) n')->groupBy('r.status')->pluck('n', 'status')->map(fn ($n): int => (int) $n)->all();
        $this->actAs($this->manager, 'manager');
        $response = $this->withHeader('X-School-Id', (string) $this->actingSchoolId)->get('/attendance/reports?type=lesson_types&date_from=2026-10-01&date_to=2026-10-31&academic_year_id='.$this->yearId);
        $this->ops['crosscheck'] = ($this->ops['crosscheck'] ?? 0) + 1;
        $rows = $response->viewData('page')['props']['report']['rows'] ?? [];
        $sum = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'left_early' => 0];
        foreach ($rows as $row) {
            foreach ($sum as $key => $_) {
                $sum[$key] += (int) $row[$key];
            }
        }
        $expected = ['present' => $raw[1] ?? 0, 'absent' => $raw[2] ?? 0, 'late' => $raw[3] ?? 0, 'excused' => $raw[4] ?? 0, 'left_early' => $raw[5] ?? 0];
        if ($sum !== $expected) {
            $this->violations[] = 'report totals '.json_encode($sum).' ≠ records '.json_encode($expected);
        }

        $this->actAs($this->foreignManager, 'foreign');
        $foreign = $this->withHeader('X-School-Id', (string) $this->actingSchoolId)->get('/attendance/review?date_from=2026-10-11&date_to=2026-10-15&sheet_status=');
        $this->ops['crosscheck'] = ($this->ops['crosscheck'] ?? 0) + 1;
        if (($foreign->viewData('page')['props']['board']['meta']['total'] ?? -1) !== 0) {
            $this->violations[] = 'another school sees this school\'s sheets';
        }
    }

    // ── Plumbing ─────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * One request + the checks that follow every request.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function hit(string $kind, string $method, string $url, array $data = [], ?string $key = null, array $headers = []): TestResponse
    {
        $this->ops[$kind] = ($this->ops[$kind] ?? 0) + 1;
        $headers += ['X-School-Id' => (string) $this->actingSchoolId] + ($key !== null ? ['X-Idempotency-Key' => $key] : []);
        $this->flushHeaders();
        $response = $method === 'get' ? $this->withHeaders($headers)->get($url) : $this->withHeaders($headers)->from('/attendance')->{$method}($url, $data);
        $this->flushHeaders();

        if ($response->getStatusCode() >= 500) {
            $this->violations[] = "{$kind} {$method} {$url} → HTTP {$response->getStatusCode()}: ".mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 200);
        }
        $error = session('error');
        if (is_string($error) && $error !== '') {
            $code = explode('?', $error)[0];
            $this->codesMet[$code] = ($this->codesMet[$code] ?? 0) + 1;
            if (! isset($this->codes[$code])) {
                $this->violations[] = "{$kind}: error code «{$code}» has no Arabic dialog text";
            }
        }
        $exception = $response->exception;
        if ($exception instanceof SisDomainException && str_contains($exception->errorCode(), 'not_found')) {
            $this->codesMet[$exception->errorCode()] = ($this->codesMet[$exception->errorCode()] ?? 0) + 1;
        }
        session()->forget(['error', 'success', 'errors']);
        $this->checkInvariants($kind);

        return $response;
    }

    private function checkInvariants(string $after): void
    {
        $bad = DB::selectOne('SELECT
            (SELECT count(*) FROM attendance.sessions WHERE school_id = ? AND (sheet_status < 1 OR sheet_status > 6)) AS bad_status,
            (SELECT count(*) FROM attendance.sessions s WHERE s.school_id = ? AND s.sheet_status IN (2, 3, 4)
                AND EXISTS (SELECT 1 FROM attendance.records r WHERE r.session_id = s.id AND r.status = 0)) AS incomplete_submitted,
            (SELECT count(*) FROM attendance.records r JOIN attendance.excuses x ON x.id = r.excuse_id WHERE r.school_id = ? AND (r.status <> 4 OR x.status <> 2)) AS bad_excuse_link,
            (SELECT count(*) FROM attendance.sessions s WHERE s.school_id = ? AND s.version < COALESCE((SELECT max(e.version) FROM attendance.sheet_events e WHERE e.session_id = s.id), 0)) AS version_behind,
            (SELECT count(*) FROM (SELECT schedule_id, session_date FROM attendance.sessions WHERE school_id = ? AND schedule_id IS NOT NULL AND status <> 3 GROUP BY 1, 2 HAVING count(*) > 1) d) AS duplicate_sheets', array_fill(0, 5, $this->schoolId));
        foreach ((array) $bad as $check => $n) {
            if ((int) $n > 0) {
                $this->violations[] = "after {$after}: {$check} = {$n}";
            }
        }
        // Event chain: each event starts where the previous one ended, along an allowed edge.
        $events = DB::table('attendance.sheet_events')->where('school_id', $this->schoolId)->orderBy('session_id')->orderBy('id')->get(['session_id', 'from_status', 'to_status']);
        $last = [];
        foreach ($events as $e) {
            $from = (int) ($e->from_status ?? 0);
            $to = (int) $e->to_status;
            if (! in_array("{$from}>{$to}", self::EDGES, true)) {
                $this->violations[] = "after {$after}: sheet {$e->session_id} moved {$from}→{$to}";
            }
            if (isset($last[$e->session_id]) && $last[$e->session_id] !== $from) {
                $this->violations[] = "after {$after}: sheet {$e->session_id} event chain broken ({$last[$e->session_id]} then {$from})";
            }
            $last[$e->session_id] = $to;
        }
        $this->violations = array_values(array_unique($this->violations));
    }

    private function actAs(User $user, string $role): void
    {
        Sanctum::actingAs($user);
        $this->actingAs($user);
        $this->actingSchoolId = $role === 'foreign' ? $this->foreignSchoolId : $this->schoolId;
    }

    private function atLesson(string $date, int $period): void
    {
        [$h, $m] = array_map('intval', explode(':', self::PERIODS[$period][0]));
        // Baghdad is UTC+3: ten minutes into the lesson.
        Carbon::setTestNow(Carbon::parse($date.' 00:00:00', 'UTC')->addMinutes($h * 60 + $m + 10 - 180));
    }

    /** @param array<string, mixed> $lesson */
    private function otherTeacher(array $lesson): ?array
    {
        foreach ($this->teachers as $teacher) {
            if ($teacher['teacher_id'] !== $lesson['teacher']['teacher_id'] && $teacher['teacher_id'] !== ($lesson['co']['teacher_id'] ?? 0)) {
                return $teacher;
            }
        }

        return null;
    }

    private function version(int $sessionId): int
    {
        return (int) DB::table('attendance.sessions')->where('id', $sessionId)->value('version');
    }

    /**
     * @param  list<int>  $enrollments
     * @param  list<int>  $statuses
     * @return array<string, mixed>
     */
    private function marks(int $version, array $enrollments, array $statuses, bool $confirm = false): array
    {
        return $this->marksPayload($version, $enrollments, $statuses, $confirm);
    }

    /** @return array<string, string> every `domain.code` key of the Arabic error dictionary. */
    private function arabicErrorCodes(): array
    {
        $source = (string) file_get_contents(base_path('resources/js/i18n/ar.ts'));
        preg_match_all("/'([a-z_]+\\.[a-z0-9_.]+)':\\s*\\n?\\s*'([^']*)'/u", $source, $matches);

        return array_combine($matches[1], $matches[2]);
    }
}
