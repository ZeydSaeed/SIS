<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U6 — seating a class / section into an exam session: only active students who take the session's subject,
 * one CreateExamEnrollment per student, idempotent, refused on tampered / foreign / cancelled targets.
 */
final class ExamSeatingPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-SEAT', 'Seating');
        $g = $this->seedExamGradeGraph($school, suffix: 'SEAT');
        $class = (int) $this->createClassForSchool($school, $g['year_id'])->id;
        $x = (int) $this->createSectionForClass($class)->id;
        $y = (int) $this->createSectionForClass($class)->id;

        $student = function (int $section, bool $takesSubject, int $status = 1, ?int $classId = null) use ($school, $g, $class): int {
            $e = $this->createActiveEnrollmentForSchool($school, $g['year_id']);
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $e->id)
                ->update(['class_id' => $classId ?? $class, 'section_id' => $section, 'status' => $status]);
            if ($takesSubject) {
                DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))->insert([
                    'enrollment_id' => $e->id, 'subject_id' => $g['subject_id'], 'is_elective' => false, 'status' => 1, 'created_at' => now(),
                ]);
            }

            return (int) $e->id;
        };

        $xTaking = [$student($x, true), $student($x, true), $student($x, true)];
        $xNoSubject = $student($x, false);
        $xSeated = $student($x, true);
        DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insert([
            'exam_session_id' => $g['session_id'], 'school_id' => $school, 'enrollment_id' => $xSeated, 'status' => 1, 'created_at' => now(),
        ]);
        $yTaking = [$student($y, true), $student($y, true)];
        $inactive = $student($x, true, status: 2);
        $otherClass = (int) $this->createClassForSchool($school, $g['year_id'])->id;
        $elsewhere = $student((int) $this->createSectionForClass($otherClass)->id, true, classId: $otherClass);

        return compact('school', 'g', 'class', 'x', 'y', 'xTaking', 'xNoSubject', 'xSeated', 'yTaking', 'inactive', 'elsewhere', 'otherClass');
    }

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    /** @param  array<string, mixed>  $s  @param  array<string, mixed>  $data */
    private function seat(array $s, array $data, ?string $key = 'seat-key', ?int $exam = null, ?int $session = null): TestResponse
    {
        $exam ??= $s['g']['exam_id'];
        $session ??= $s['g']['session_id'];

        return $this->from("/exams/{$exam}")->post("/exams/{$exam}/sessions/{$session}/seat", $data, $key !== null ? ['X-Idempotency-Key' => $key] : []);
    }

    /** @return list<int> */
    private function seated(int $session): array
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->where('exam_session_id', $session)->orderBy('enrollment_id')->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->all();
    }

    #[Test]
    public function the_manager_seats_the_active_students_of_the_class_who_take_the_subject(): void
    {
        $s = $this->scene();
        $user = $this->as('GradesManager', $s['school']);
        $before = $this->seated($s['g']['session_id']);

        $response = $this->seat($s, ['class_id' => $s['class']]);

        $response->assertRedirect("/exams/{$s['g']['exam_id']}")->assertSessionHas('success', 'flash.exams.seated');
        $this->assertSame(['seated' => 5, 'replayed' => 0, 'already_seated' => 1, 'subject_not_assigned' => 1, 'failed' => 0], $response->getSession()->get('exam_seating_result')['counts']);
        $expected = array_merge($before, $s['xTaking'], $s['yTaking']);
        sort($expected);
        $this->assertSame($expected, $this->seated($s['g']['session_id']));
        foreach (['xNoSubject', 'inactive', 'elsewhere'] as $key) {
            $this->assertNotContains($s[$key], $this->seated($s['g']['session_id']), $key);
        }
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'exams.web.seat_class')->where('actor_id', $user->id)->exists());
    }

    #[Test]
    public function one_section_seats_only_that_section(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $response = $this->seat($s, ['class_id' => $s['class'], 'section_id' => $s['y']]);

        $this->assertSame(2, $response->getSession()->get('exam_seating_result')['counts']['seated']);
        foreach ($s['yTaking'] as $id) {
            $this->assertContains($id, $this->seated($s['g']['session_id']));
        }
        foreach ($s['xTaking'] as $id) {
            $this->assertNotContains($id, $this->seated($s['g']['session_id']));
        }
    }

    #[Test]
    public function repeating_the_request_never_creates_a_second_seat(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->seat($s, ['class_id' => $s['class']], 'seat-repeat');
        $after = $this->seated($s['g']['session_id']);

        $again = $this->seat($s, ['class_id' => $s['class']], 'seat-repeat');
        $other = $this->seat($s, ['class_id' => $s['class']], 'seat-repeat-new-key');

        $this->assertSame($after, $this->seated($s['g']['session_id']));
        $this->assertSame(0, $again->getSession()->get('exam_seating_result')['counts']['seated']);
        $this->assertSame(6, $other->getSession()->get('exam_seating_result')['counts']['already_seated']);
        $this->assertSame(count($after), count(array_unique($after)));
    }

    #[Test]
    public function invalid_targets_are_refused_and_seat_nobody(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $before = $this->seated($s['g']['session_id']);

        $this->seat($s, ['class_id' => $s['class'], 'section_id' => (int) $this->createSectionForClass($s['otherClass'])->id])
            ->assertSessionHas('error', 'exams.seating.section_invalid');
        $otherYear = $this->createAcademicYear('AY-SEAT-OTHER');
        $this->seat($s, ['class_id' => (int) $this->createClassForSchool($s['school'], $otherYear)->id])
            ->assertSessionHas('error', 'exams.seating.class_invalid');
        $this->seat($s, ['class_id' => $s['class']], key: null)
            ->assertSessionHas('error', 'exams.seating.idempotency_key_required');
        $this->seat($s, ['class_id' => $s['class'], 'enrollment_ids' => [$s['xNoSubject']]])->assertSessionHasErrors('enrollment_ids');

        $this->assertSame($before, $this->seated($s['g']['session_id']));
    }

    #[Test]
    public function a_session_of_another_exam_or_school_is_not_found_and_a_cancelled_one_is_refused(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $otherExam = $this->seedExamGradeGraph($s['school'], $s['g']['year_id'], 'SEAT2');
        $foreignSchool = $this->createSchool('SCH-SEAT-F', 'Foreign');
        $foreign = $this->seedExamGradeGraph($foreignSchool, $s['g']['year_id'], 'SEATF');

        $this->seat($s, ['class_id' => $s['class']], session: $otherExam['session_id'])->assertNotFound();
        $this->seat($s, ['class_id' => $s['class']], exam: $foreign['exam_id'], session: $foreign['session_id'])->assertNotFound();

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $s['g']['session_id'])->update(['status' => ExamSessionStatus::Cancelled->value]);
        $before = $this->seated($s['g']['session_id']);
        $this->seat($s, ['class_id' => $s['class']])->assertSessionHas('error', 'exams.seating.session_cancelled');
        $this->assertSame($before, $this->seated($s['g']['session_id']));
        $this->assertSame([$otherExam['enrollment_id']], $this->seated($otherExam['session_id']), 'the other exam keeps only its own seat');
    }

    #[Test]
    public function users_without_exam_enrollment_rights_are_refused(): void
    {
        $s = $this->scene();
        foreach (['GradesTeacher', 'GradesViewer'] as $role) {
            $this->as($role, $s['school']);
            $before = $this->seated($s['g']['session_id']);
            $this->seat($s, ['class_id' => $s['class']], "seat-{$role}")->assertForbidden();
            $this->assertSame($before, $this->seated($s['g']['session_id']), $role);
        }
    }

    #[Test]
    public function the_exam_page_offers_seating_only_to_users_who_may_seat(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->seat($s, ['class_id' => $s['class']], 'seat-page');

        $this->get("/exams/{$s['g']['exam_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('exams/show')
            ->where('seating.can_seat', true)
            ->where('seating.classes', fn ($classes) => collect($classes)->pluck('id')->contains($s['class']))
            ->where("seating.seat_counts.{$s['g']['session_id']}", 7));

        $this->as('GradesTeacher', $s['school']);
        $this->get("/exams/{$s['g']['exam_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seating.can_seat', false)
            ->where('seating.classes', []));
    }

    #[Test]
    public function seated_students_appear_on_the_grade_sheet(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $this->seat($s, ['class_id' => $s['class']], 'seat-sheet');

        $this->get("/grades/sheet?academic_year_id={$s['g']['year_id']}&session_id={$s['g']['session_id']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows', 7));
    }
}
