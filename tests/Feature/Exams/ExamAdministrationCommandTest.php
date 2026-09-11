<?php

namespace Tests\Feature\Exams;

use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\UpdateExamCommand;
use App\Application\Exams\Commands\UpdateExamHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class ExamAdministrationCommandTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function create_exam_persists_outbox_and_idempotency_same_commit(): void
    {
        $schoolId = $this->createSchool('SCH-EX-C', 'Exam Create School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);

        $yearId = $this->createAcademicYear('AY-EX-C');
        [$termId, $typeId] = $this->seedTermAndType($yearId, 'C');

        $handler = $this->app->make(CreateExamHandler::class);
        $result = $handler->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'Midterm Create',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-exam-1',
        ));

        $this->assertTrue($result->success);
        $this->assertSame(ExamStatus::Draft->value, $result->status);
        $this->assertNotNull($result->examId);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exams'), [
            'id' => $result->examId,
            'school_id' => $schoolId,
            'name' => 'Midterm Create',
            'status' => ExamStatus::Draft->value,
        ]);

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'outbox_messages'))
                ->where('event_type', \App\Domain\Exams\Events\ExamCreated::class)
                ->exists()
        );

        $this->assertTrue(
            DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
                ->where('key', 'create-exam-1')
                ->where('command_name', CreateExamHandler::COMMAND_NAME)
                ->exists()
        );

        $replay = $handler->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'Midterm Create',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-exam-1',
        ));

        $this->assertTrue($replay->fromIdempotencyCache);
        $this->assertSame($result->examId, $replay->examId);
        $this->assertSame(1, DB::table(SchemaHelper::qualified('exams', 'exams'))->where('name', 'Midterm Create')->count());
    }

    #[Test]
    public function create_exam_rejects_conflicting_idempotency_payload(): void
    {
        $schoolId = $this->createSchool('SCH-EX-CF', 'Exam Conflict School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $yearId = $this->createAcademicYear('AY-EX-CF');
        [$termId, $typeId] = $this->seedTermAndType($yearId, 'CF');

        $handler = $this->app->make(CreateExamHandler::class);
        $handler->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'Original',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-conflict',
        ));

        $this->expectException(IdempotencyPayloadConflictException::class);
        $handler->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'Different',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $user->id,
            idempotencyKey: 'create-conflict',
        ));
    }

    #[Test]
    public function update_exam_respects_dr003_allowlist(): void
    {
        $schoolId = $this->createSchool('SCH-EX-U', 'Exam Update School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'U');

        DB::table(SchemaHelper::qualified('exams', 'exams'))
            ->where('id', $graph['exam_id'])
            ->update(['status' => ExamStatus::Scheduled->value]);

        $handler = $this->app->make(UpdateExamHandler::class);
        $ok = $handler->handle(new UpdateExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'upd-name',
            name: 'Renamed Exam',
        ));
        $this->assertTrue($ok->success);

        $this->expectException(ExamUpdateForbiddenException::class);
        $handler->handle(new UpdateExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'upd-type',
            examTypeId: $graph['term_id'],
        ));
    }

    #[Test]
    public function cancel_exam_cascades_and_blocks_on_current_grade(): void
    {
        $schoolId = $this->createSchool('SCH-EX-X', 'Exam Cancel School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'X');

        DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))
            ->where('id', $graph['session_id'])
            ->update(['status' => ExamSessionStatus::InProgress->value]);

        $handler = $this->app->make(CancelExamHandler::class);
        $result = $handler->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-ok',
        ));

        $this->assertSame(ExamStatus::Cancelled->value, $result->status);
        $this->assertContains($graph['session_id'], $result->cancelledSessionIds);
        $this->assertContains($graph['exam_enrollment_id'], $result->withdrawnEnrollmentIds);

        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_sessions'), [
            'id' => $graph['session_id'],
            'status' => ExamSessionStatus::Cancelled->value,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('exams', 'exam_enrollments'), [
            'id' => $graph['exam_enrollment_id'],
            'status' => ExamEnrollmentStatus::Withdrawn->value,
        ]);

        $graph2 = $this->seedExamGradeGraph($schoolId, suffix: 'XG');
        $this->app->make(\App\Application\Exams\Commands\EnterStudentGradeHandler::class)->handle(
            new \App\Application\Exams\Commands\EnterStudentGradeCommand(
                schoolId: $schoolId,
                examEnrollmentId: $graph2['exam_enrollment_id'],
                score: '80',
                isAbsent: false,
                enteredBy: (int) $user->id,
                idempotencyKey: 'enter-for-cancel-block',
            )
        );

        $this->expectException(ExamCancelBlockedException::class);
        $handler->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph2['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cancel-blocked',
        ));
    }

    #[Test]
    public function unauthorized_role_is_denied_and_missing_school_context_fails_closed(): void
    {
        $schoolId = $this->createSchool('SCH-EX-D', 'Exam Deny School');
        $viewer = $this->actingAsGradesViewer(schoolId: $schoolId);
        $this->bindSchool($schoolId);
        $yearId = $this->createAcademicYear('AY-EX-D');
        [$termId, $typeId] = $this->seedTermAndType($yearId, 'D');

        $handler = $this->app->make(CreateExamHandler::class);
        try {
            $handler->handle(new CreateExamCommand(
                schoolId: $schoolId,
                academicYearId: $yearId,
                termId: $termId,
                examTypeId: $typeId,
                name: 'Denied',
                startDate: '2026-11-01',
                endDate: '2026-11-15',
                actorUserId: (int) $viewer->id,
                idempotencyKey: 'denied-create',
            ));
            $this->fail('Expected ExamAuthorityDeniedException');
        } catch (ExamAuthorityDeniedException) {
            $this->assertTrue(true);
        }

        $this->app->make(SchoolContext::class)->clear();
        $manager = $this->actingAsGradesManagerForSchool($schoolId);
        $this->expectException(ExamAuthorityDeniedException::class);
        $handler->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'No Context',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $manager->id,
            idempotencyKey: 'no-ctx',
        ));
    }

    #[Test]
    public function exam_policy_allows_grades_manager_and_http_routes_absent(): void
    {
        $schoolId = $this->createSchool('SCH-EX-P', 'Exam Policy School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->bindSchool($schoolId);

        $this->assertTrue(Gate::forUser($user)->allows('create', \App\Infrastructure\Persistence\Eloquent\ExamRecord::class));

        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri())->implode(' ');
        $this->assertStringNotContainsString('api/v1/exams', $routes);
        $this->assertFalse(class_exists(\App\Http\Controllers\Api\ExamController::class));
        $this->assertFalse(class_exists(\App\Application\Exams\Commands\CompleteExamHandler::class));
        $this->assertFalse(class_exists(\App\Application\Exams\Commands\CreateExamSessionHandler::class));
    }

    private function bindSchool(int $schoolId): void
    {
        $this->app->make(SchoolContext::class)->set($schoolId);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedTermAndType(int $yearId, string $suffix): array
    {
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T-'.$suffix.uniqid(),
            'name' => 'Term '.$suffix,
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'TX'.substr(uniqid(), -4),
            'name' => 'Type '.$suffix,
            'weight_percentage' => 40,
        ]);

        return [$termId, $typeId];
    }
}
