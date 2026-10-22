<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Infrastructure\Persistence\Eloquent\OutboxMessageRecord;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Models\User;
use App\Security\Audit\SecurityEventType;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U3 — exam-session grade-entry sheet: server-scoped roster, per-row outcomes, official write path only.
 */
final class GradeEntrySheetPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /**
     * Session S1 (subject M): seat A (branch B, class C, section X — covered for the teacher), seat B (section Y — not
     * covered), seat W (withdrawn). Session S2 (another subject, same year). Session F in another school.
     *
     * @return array<string, int>
     */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-GES', 'Grade sheet');
        $g = $this->seedExamGradeGraph($school, suffix: 'GES');
        $this->markSessionInProgressForGradeEntry($g['session_id']);
        $branch = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $school, 'code' => 'BR-GES-'.substr(uniqid(), -5), 'name' => 'فرع', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $class = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $g['enrollment_id'])->value('class_id');
        $sectionX = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $g['enrollment_id'])->value('section_id');
        $sectionY = (int) $this->createSectionForClass($class)->id;
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $g['enrollment_id'])->update(['branch_id' => $branch]);

        $seatB = $this->seat($school, $g, $branch, $class, $sectionY);
        $seatW = $this->seat($school, $g, $branch, $class, $sectionX, ExamEnrollmentStatus::Withdrawn->value);

        $other = $this->seedExamGradeGraph($school, $g['year_id'], 'GES2');
        $this->markSessionInProgressForGradeEntry($other['session_id']);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $other['enrollment_id'])->update(['branch_id' => $branch]);

        $foreignSchool = $this->createSchool('SCH-GES-F', 'Foreign');
        $foreign = $this->seedExamGradeGraph($foreignSchool, $g['year_id'], 'GESF');
        $this->markSessionInProgressForGradeEntry($foreign['session_id']);

        return [
            'school' => $school, 'year' => $g['year_id'], 'subject' => $g['subject_id'], 'branch' => $branch,
            'class' => $class, 'section_x' => $sectionX, 'session' => $g['session_id'],
            'seat_a' => $g['exam_enrollment_id'], 'seat_b' => $seatB, 'seat_w' => $seatW,
            'session_2' => $other['session_id'], 'seat_2' => $other['exam_enrollment_id'],
            'foreign_session' => $foreign['session_id'], 'foreign_seat' => $foreign['exam_enrollment_id'],
        ];
    }

    /** @param  array<string, mixed>  $g */
    private function seat(int $school, array $g, int $branch, int $class, int $section, int $status = 1): int
    {
        $enrollment = $this->createActiveEnrollmentForSchool($school, $g['year_id']);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $enrollment->id)
            ->update(['branch_id' => $branch, 'class_id' => $class, 'section_id' => $section]);

        return (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
            'exam_session_id' => $g['session_id'], 'school_id' => $school, 'enrollment_id' => $enrollment->id,
            'seat_number' => 'S'.$enrollment->id, 'status' => $status, 'created_at' => now(),
        ]);
    }

    /** Teacher of subject M for section X only. */
    private function teacher(array $s): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesTeacher($user, $s['school']);
        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'user_id' => $user->id, 'employee_code' => 'EMP-'.uniqid(), 'first_name' => 'معلم', 'last_name' => 'الدرجات',
            'full_name' => 'معلم الدرجات', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $teacherId, 'school_id' => $s['school'], 'academic_year_id' => $s['year'], 'is_primary' => true,
        ]);
        DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))->insert([
            'teacher_id' => $teacherId, 'school_id' => $s['school'], 'academic_year_id' => $s['year'], 'subject_id' => $s['subject'],
            'branch_id' => $s['branch'], 'class_id' => $s['class'], 'section_id' => $s['section_x'], 'status' => 1,
            'effective_from' => now()->subMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->web($user, $s['school']);
    }

    private function manager(array $s): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($user, $s['school']);

        return $this->web($user, $s['school']);
    }

    private function web(User $user, int $schoolId): User
    {
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $schoolId]);

        return $user;
    }

    private function sheetUrl(array $s, ?int $session = null): string
    {
        return '/grades/sheet?academic_year_id='.$s['year'].($session !== null ? '&session_id='.$session : '');
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function save(array $s, array $rows, ?string $key = 'ges-key', ?int $session = null): TestResponse
    {
        $session ??= $s['session'];

        return $this->from($this->sheetUrl($s, $session))
            ->post("/grades/sheet/{$session}", ['rows' => $rows], $key !== null ? ['X-Idempotency-Key' => $key] : []);
    }

    /** @return array<int, string> seat id → outcome status */
    private function outcomes(TestResponse $response): array
    {
        $outcomes = $response->getSession()->get('grade_sheet_outcomes') ?? [];

        return array_column($outcomes, 'status', 'exam_enrollment_id');
    }

    private function grades(int $seat): int
    {
        return DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $seat)->count();
    }

    #[Test]
    public function teacher_sees_only_covered_sessions_and_seats(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $this->get($this->sheetUrl($s, $s['session']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('grades/sheet')
                ->where('scope.school_wide', false)
                ->has('sessions', 1)
                ->where('sessions.0.id', $s['session'])
                ->has('rows', 1)
                ->where('rows.0.exam_enrollment_id', $s['seat_a'])
                ->where('rows.0.grade_id', null)
                ->where('session.max_grade', 100));
    }

    #[Test]
    public function manager_sees_every_active_seat_of_the_school(): void
    {
        $s = $this->scene();
        $this->manager($s);

        $this->get($this->sheetUrl($s, $s['session']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.school_wide', true)
                ->has('sessions', 2)
                ->has('rows', 2) // A and B; the withdrawn seat is not gradable
                ->where('rows', fn ($rows) => collect($rows)->pluck('exam_enrollment_id')->sort()->values()->all()
                    === collect([$s['seat_a'], $s['seat_b']])->sort()->values()->all()));
    }

    #[Test]
    public function teacher_opening_an_uncovered_session_is_forbidden_and_audited(): void
    {
        $s = $this->scene();
        $user = $this->teacher($s);

        $this->get($this->sheetUrl($s, $s['session_2']))->assertForbidden();
        $this->assertTrue(SecurityAuditLogRecord::query()
            ->where('event_id', SecurityEventType::IdorBlocked->value)
            ->where('actor_id', $user->id)
            ->where('action', 'grades.sheet.view')
            ->exists());
    }

    #[Test]
    public function unknown_or_other_school_session_is_not_found(): void
    {
        $s = $this->scene();
        $this->manager($s);

        $this->get($this->sheetUrl($s, 999999))->assertNotFound();
        $this->get($this->sheetUrl($s, $s['foreign_session']))->assertNotFound();
    }

    #[Test]
    public function teacher_saves_a_covered_row_through_the_official_path(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $response = $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '77.5', 'is_absent' => false]]);

        $response->assertRedirect($this->sheetUrl($s, $s['session']))->assertSessionHas('success', 'flash.grades.sheetSaved');
        $this->assertSame([$s['seat_a'] => 'saved'], $this->outcomes($response));
        $row = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $s['seat_a'])->first();
        $this->assertSame('77.50', (string) $row->score);
        $this->assertTrue((bool) $row->is_current);
        $this->assertSame(2, (int) $row->status); // Entered — not finalized
        $this->assertSame(1, OutboxMessageRecord::query()
            ->where('event_type', StudentGradeEntered::class)
            ->where('payload->grade_id', (int) $row->id)
            ->count());
    }

    #[Test]
    public function modified_identifiers_only_produce_per_row_denials(): void
    {
        $s = $this->scene();
        $user = $this->teacher($s);

        $response = $this->save($s, [
            ['exam_enrollment_id' => $s['seat_a'], 'score' => '60', 'is_absent' => false],
            ['exam_enrollment_id' => $s['seat_b'], 'score' => '60', 'is_absent' => false],       // same session, not covered
            ['exam_enrollment_id' => $s['seat_2'], 'score' => '60', 'is_absent' => false],       // another session
            ['exam_enrollment_id' => $s['foreign_seat'], 'score' => '60', 'is_absent' => false], // another school
            ['exam_enrollment_id' => $s['seat_w'], 'score' => '60', 'is_absent' => false],       // withdrawn seat
        ]);

        $response->assertSessionHas('error', 'flash.grades.sheetPartial');
        $this->assertSame([
            $s['seat_a'] => 'saved',
            $s['seat_b'] => 'grades.sheet.seat_forbidden',
            $s['seat_2'] => 'grades.sheet.seat_not_in_session',
            $s['foreign_seat'] => 'grades.sheet.seat_not_in_session',
            $s['seat_w'] => 'grades.sheet.seat_not_in_session',
        ], $this->outcomes($response));
        foreach (['seat_b', 'seat_2', 'foreign_seat', 'seat_w'] as $seat) {
            $this->assertSame(0, $this->grades($s[$seat]), $seat);
        }
        $this->assertTrue(SecurityAuditLogRecord::query()
            ->where('event_id', SecurityEventType::IdorBlocked->value)->where('actor_id', $user->id)
            ->where('action', 'grades.enter.scope')->where('target_id', (string) $s['seat_b'])->exists());
    }

    #[Test]
    public function teacher_posting_to_an_uncovered_session_saves_nothing(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $response = $this->save($s, [['exam_enrollment_id' => $s['seat_2'], 'score' => '50', 'is_absent' => false]], session: $s['session_2']);

        $this->assertSame([$s['seat_2'] => 'grades.sheet.seat_forbidden'], $this->outcomes($response));
        $this->assertSame(0, $this->grades($s['seat_2']));
    }

    #[Test]
    public function manager_saves_rows_across_sections_including_absence(): void
    {
        $s = $this->scene();
        $this->manager($s);

        $response = $this->save($s, [
            ['exam_enrollment_id' => $s['seat_a'], 'score' => '91', 'is_absent' => false],
            ['exam_enrollment_id' => $s['seat_b'], 'score' => null, 'is_absent' => true],
        ]);

        $this->assertSame([$s['seat_a'] => 'saved', $s['seat_b'] => 'saved'], $this->outcomes($response));
        $absent = DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $s['seat_b'])->first();
        $this->assertTrue((bool) $absent->is_absent);
        $this->assertNull($absent->score);
    }

    #[Test]
    public function repeated_submission_replays_instead_of_duplicating(): void
    {
        $s = $this->scene();
        $this->teacher($s);
        $rows = [['exam_enrollment_id' => $s['seat_a'], 'score' => '70', 'is_absent' => false]];

        $this->save($s, $rows, 'ges-same');
        $second = $this->save($s, $rows, 'ges-same');

        $this->assertSame([$s['seat_a'] => 'replayed'], $this->outcomes($second));
        $second->assertSessionHas('success', 'flash.grades.sheetSaved');
        $this->assertSame(1, $this->grades($s['seat_a']));
    }

    #[Test]
    public function duplicate_rows_and_existing_grades_never_overwrite(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $first = $this->save($s, [
            ['exam_enrollment_id' => $s['seat_a'], 'score' => '40', 'is_absent' => false],
            ['exam_enrollment_id' => $s['seat_a'], 'score' => '99', 'is_absent' => false],
        ], 'ges-dup-1');
        $this->assertSame(['saved', 'grades.sheet.duplicate_row'], array_column($first->getSession()->get('grade_sheet_outcomes'), 'status'));

        // A new submission for a seat that already has a current grade is refused (correction is a separate, authorized path).
        $second = $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '95', 'is_absent' => false]], 'ges-dup-2');
        $this->assertSame([$s['seat_a'] => 'grades.conflict'], $this->outcomes($second));
        $this->assertSame(1, $this->grades($s['seat_a']));
        $this->assertSame('40.00', (string) DB::table(SchemaHelper::qualified('exams', 'student_grades'))->where('exam_enrollment_id', $s['seat_a'])->value('score'));
    }

    #[Test]
    public function score_above_the_session_maximum_is_a_row_error(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $response = $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '100.01', 'is_absent' => false]]);

        $this->assertSame([$s['seat_a'] => 'grades.validation'], $this->outcomes($response));
        $this->assertSame(0, $this->grades($s['seat_a']));
    }

    #[Test]
    public function malformed_payloads_are_rejected_before_any_write(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '-1', 'is_absent' => false]])->assertSessionHasErrors('rows.0.score');
        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '50.123', 'is_absent' => false]])->assertSessionHasErrors('rows.0.score');
        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '50', 'is_absent' => false, 'max_score' => 10]])->assertSessionHasErrors();
        $this->save($s, [])->assertSessionHasErrors('rows');
        $this->assertSame(0, $this->grades($s['seat_a']));
    }

    #[Test]
    public function missing_idempotency_key_saves_nothing(): void
    {
        $s = $this->scene();
        $this->teacher($s);

        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '50', 'is_absent' => false]], key: null)
            ->assertSessionHas('error', 'grades.idempotency_key_required');
        $this->assertSame(0, $this->grades($s['seat_a']));
    }

    #[Test]
    public function session_not_open_for_entry_rejects_each_row(): void
    {
        $s = $this->scene();
        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $s['session'])->update(['status' => ExamSessionStatus::Scheduled->value]);
        $this->manager($s);

        $response = $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '50', 'is_absent' => false]]);

        $this->assertSame([$s['seat_a'] => 'grades.session_unavailable'], $this->outcomes($response));
        $this->assertSame(0, $this->grades($s['seat_a']));
    }

    #[Test]
    public function stored_grades_are_shown_read_only_in_the_roster(): void
    {
        $s = $this->scene();
        $this->teacher($s);
        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '88', 'is_absent' => false]]);

        $this->get($this->sheetUrl($s, $s['session']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.score', '88.00')
                ->where('rows.0.grade_status', 2)
                ->whereNot('rows.0.grade_id', null));
    }

    #[Test]
    public function user_without_grade_entry_permission_is_denied(): void
    {
        $s = $this->scene();
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesViewer($user, $s['school']);
        $this->web($user, $s['school']);

        $this->get($this->sheetUrl($s, $s['session']))->assertForbidden();
        $this->save($s, [['exam_enrollment_id' => $s['seat_a'], 'score' => '50', 'is_absent' => false]])->assertForbidden();
        $this->assertSame(0, $this->grades($s['seat_a']));
    }

    #[Test]
    public function old_raw_id_form_route_now_opens_the_sheet(): void
    {
        $s = $this->scene();
        $this->manager($s);

        $this->get('/grades/enter?academic_year_id='.$s['year'])->assertRedirect('/grades/sheet?academic_year_id='.$s['year']);
    }
}
