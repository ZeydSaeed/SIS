<?php

namespace Tests\Feature\Security;

use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\UpdateExamCommand;
use App\Application\Exams\Commands\UpdateExamHandler;
use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;
use Tests\TestCase;

final class ExamAdministrationAuthorizationTest extends TestCase
{
    use InteractsWithSecurity;
    use RefreshDatabase;
    use SeedsApplicationExamGradeGraph;

    #[Test]
    public function grades_manager_has_exam_permissions_and_policy_access(): void
    {
        $schoolId = $this->createSchool('SCH-EX-AUTH', 'Exam Auth School');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);

        $auth = $this->app->make(\App\Security\Authorization\Contracts\AuthorizationServiceInterface::class);
        $this->assertTrue($auth->userHasPermission($user, Permission::EXAM_CREATE));
        $this->assertTrue($auth->userHasPermission($user, Permission::EXAM_UPDATE));
        $this->assertTrue($auth->userHasPermission($user, Permission::EXAM_CANCEL));

        $this->assertTrue(Gate::forUser($user)->allows('create', ExamRecord::class));
    }

    #[Test]
    public function grades_teacher_cannot_create_or_cancel_exam(): void
    {
        $schoolId = $this->createSchool('SCH-EX-TCH', 'Exam Teacher School');
        $teacher = $this->actingAsGradesTeacher(schoolId: $schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);

        $auth = $this->app->make(\App\Security\Authorization\Contracts\AuthorizationServiceInterface::class);
        $this->assertFalse($auth->userHasPermission($teacher, Permission::EXAM_CREATE));
        $this->assertFalse($auth->userHasPermission($teacher, Permission::EXAM_CANCEL));

        $yearId = $this->createAcademicYear('AY-EX-TCH');
        $termId = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $yearId,
            'code' => 'T-TCH'.uniqid(),
            'name' => 'Term TCH',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-31',
            'term_order' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'TT'.substr(uniqid(), -4),
            'name' => 'Type TCH',
            'weight_percentage' => 40,
        ]);

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CreateExamHandler::class)->handle(new CreateExamCommand(
            schoolId: $schoolId,
            academicYearId: $yearId,
            termId: $termId,
            examTypeId: $typeId,
            name: 'Teacher Denied',
            startDate: '2026-11-01',
            endDate: '2026-11-15',
            actorUserId: (int) $teacher->id,
            idempotencyKey: 'teacher-denied',
        ));
    }

    #[Test]
    public function cross_school_mutation_is_denied(): void
    {
        $schoolA = $this->createSchool('SCH-EX-A', 'Exam School A');
        $schoolB = $this->createSchool('SCH-EX-B', 'Exam School B');
        $user = $this->actingAsGradesManagerForSchool($schoolA);
        $this->app->make(SchoolContext::class)->set($schoolA);

        $graphB = $this->seedExamGradeGraph($schoolB, suffix: 'XB');

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(UpdateExamHandler::class)->handle(new UpdateExamCommand(
            schoolId: $schoolB,
            examId: $graphB['exam_id'],
            actorUserId: (int) $user->id,
            idempotencyKey: 'cross-school',
            name: 'Hijack',
        ));
    }

    #[Test]
    public function no_wildcard_or_query_permissions_registered(): void
    {
        $permissions = array_keys(config('security.permissions'));
        $this->assertContains('exam.create', $permissions);
        $this->assertContains('exam.update', $permissions);
        $this->assertContains('exam.cancel', $permissions);
        $this->assertNotContains('exam.*', $permissions);
        $this->assertNotContains('exam.view', $permissions);
        $this->assertNotContains('exam.read', $permissions);
        $this->assertNotContains('exam.list', $permissions);
        $this->assertNotContains('exam.show', $permissions);
        $this->assertNotContains('exam.delete', $permissions);
        $this->assertNotContains('exam.session.update', $permissions);
        $this->assertNotContains('exam.session.cancel', $permissions);

        $this->assertContains('exam.create', config('security.roles.grades_manager'));
        $this->assertContains('exam.update', config('security.roles.grades_manager'));
        $this->assertContains('exam.cancel', config('security.roles.grades_manager'));
        $this->assertNotContains('exam.create', config('security.roles.grades_teacher'));
    }

    #[Test]
    public function cancel_requires_dedicated_permission_not_update(): void
    {
        $schoolId = $this->createSchool('SCH-EX-CAN', 'Exam Cancel Auth');
        $user = $this->actingAsGradesManagerForSchool($schoolId);
        $this->app->make(SchoolContext::class)->set($schoolId);
        $graph = $this->seedExamGradeGraph($schoolId, suffix: 'CAN');

        // grades_manager has both; verify cancel ability is distinct in policy
        $exam = ExamRecord::query()->find($graph['exam_id']);
        $this->assertTrue(Gate::forUser($user)->allows('cancel', $exam));
        $this->assertTrue(Gate::forUser($user)->allows('update', $exam));

        $viewer = $this->actingAsGradesViewer(schoolId: $schoolId);
        $this->assertFalse(Gate::forUser($viewer)->allows('cancel', $exam));

        $this->expectException(ExamAuthorityDeniedException::class);
        $this->app->make(CancelExamHandler::class)->handle(new CancelExamCommand(
            schoolId: $schoolId,
            examId: $graph['exam_id'],
            actorUserId: (int) $viewer->id,
            idempotencyKey: 'viewer-cancel',
        ));
    }
}
