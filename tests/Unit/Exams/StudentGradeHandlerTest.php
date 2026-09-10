<?php

namespace Tests\Unit\Exams;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Exams\Commands\VoidStudentGradeCommand;
use App\Application\Exams\Commands\VoidStudentGradeHandler;
use App\Domain\Exams\Data\ExamEnrollmentGradeContext;
use App\Domain\Exams\Data\StudentGradeSnapshot;
use App\Domain\Exams\Events\StudentGradeCorrected;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\Events\StudentGradeFinalized;
use App\Domain\Exams\Events\StudentGradeVoided;
use App\Domain\Exams\Exceptions\CurrentGradeAlreadyExistsException;
use App\Domain\Exams\Exceptions\ExamEnrollmentNotFoundException;
use App\Domain\Exams\Exceptions\InactiveExamSeatException;
use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\ValueObjects\GradeStatus;
use Tests\TestCase;

class StudentGradeHandlerTest extends TestCase
{
    public function test_enter_stages_outbox_and_inserts(): void
    {
        $context = $this->context();
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('findExamEnrollmentContext')->willReturn($context);
        $grades->method('findCurrentForExamEnrollment')->willReturn(null);
        $grades->expects($this->once())->method('insert')->willReturn(42);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(StudentGradeEntered::class));

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $handler = new EnterStudentGradeHandler($uow, $grades, $outbox, $this->createMock(IdempotencyStore::class));
        $result = $handler->handle(new EnterStudentGradeCommand(1, 10, '88', false, 5));

        $this->assertSame(42, $result->gradeId);
        $this->assertSame(2026, $result->academicYearId);
    }

    public function test_enter_rejects_missing_exam_enrollment(): void
    {
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('findExamEnrollmentContext')->willReturn(null);

        $handler = new EnterStudentGradeHandler(
            $this->createMock(UnitOfWork::class),
            $grades,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(ExamEnrollmentNotFoundException::class);
        $handler->handle(new EnterStudentGradeCommand(1, 99, '50', false));
    }

    public function test_enter_rejects_withdrawn_seat(): void
    {
        $context = $this->context(examEnrollmentStatus: 5);
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('findExamEnrollmentContext')->willReturn($context);

        $handler = new EnterStudentGradeHandler(
            $this->createMock(UnitOfWork::class),
            $grades,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(InactiveExamSeatException::class);
        $handler->handle(new EnterStudentGradeCommand(1, 10, '50', false));
    }

    public function test_enter_rejects_existing_current_grade(): void
    {
        $context = $this->context();
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('findExamEnrollmentContext')->willReturn($context);
        $grades->method('findCurrentForExamEnrollment')->willReturn($this->snapshot());

        $handler = new EnterStudentGradeHandler(
            $this->createMock(UnitOfWork::class),
            $grades,
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(CurrentGradeAlreadyExistsException::class);
        $handler->handle(new EnterStudentGradeCommand(1, 10, '50', false));
    }

    public function test_enter_idempotent_replay(): void
    {
        $store = $this->createMock(IdempotencyStore::class);
        $store->method('find')->willReturn(['grade_id' => 7, 'academic_year_id' => 2026]);

        $handler = new EnterStudentGradeHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(StudentGradeRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $store,
        );

        $result = $handler->handle(new EnterStudentGradeCommand(1, 10, '50', false, null, 'key-1'));
        $this->assertTrue($result->fromIdempotencyCache);
        $this->assertSame(7, $result->gradeId);
    }

    public function test_correct_voids_and_inserts_replacement(): void
    {
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('lockByIdentity')->willReturn($this->snapshot());
        $grades->method('correctionChainIds')->willReturn([11]);
        $grades->expects($this->once())->method('markVoided')->with(11, 2026);
        $grades->expects($this->once())->method('insert')->willReturn(12);

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(StudentGradeCorrected::class));

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $handler = new CorrectStudentGradeHandler($uow, $grades, $outbox, $this->createMock(IdempotencyStore::class));
        $result = $handler->handle(new CorrectStudentGradeCommand(1, 11, 2026, '90', false, 'typo'));

        $this->assertSame(11, $result->previousGradeId);
        $this->assertSame(12, $result->newGradeId);
    }

    public function test_correct_rejects_empty_reason(): void
    {
        $handler = new CorrectStudentGradeHandler(
            $this->createMock(UnitOfWork::class),
            $this->createMock(StudentGradeRepositoryInterface::class),
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $this->expectException(InvalidGradeCorrectionException::class);
        $handler->handle(new CorrectStudentGradeCommand(1, 11, 2026, '90', false, '  '));
    }

    public function test_void_stages_event(): void
    {
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('lockByIdentity')->willReturn($this->snapshot());
        $grades->expects($this->once())->method('markVoided');

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(StudentGradeVoided::class));

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $handler = new VoidStudentGradeHandler($uow, $grades, $outbox, $this->createMock(IdempotencyStore::class));
        $result = $handler->handle(new VoidStudentGradeCommand(1, 11, 2026, 'withdrawn'));
        $this->assertSame(11, $result->gradeId);
    }

    public function test_finalize_stages_event(): void
    {
        $grades = $this->createMock(StudentGradeRepositoryInterface::class);
        $grades->method('lockByIdentity')->willReturn($this->snapshot());
        $grades->expects($this->once())->method('markFinalized');

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(StudentGradeFinalized::class));

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('transaction')->willReturnCallback(fn (callable $cb) => $cb());

        $handler = new FinalizeStudentGradeHandler($uow, $grades, $outbox, $this->createMock(IdempotencyStore::class));
        $result = $handler->handle(new FinalizeStudentGradeCommand(1, 11, 2026));
        $this->assertSame(11, $result->gradeId);
    }

    private function context(int $examEnrollmentStatus = 1): ExamEnrollmentGradeContext
    {
        return new ExamEnrollmentGradeContext(
            examEnrollmentId: 10,
            schoolId: 1,
            examSessionId: 20,
            enrollmentId: 30,
            studentId: 40,
            subjectId: 50,
            academicYearId: 2026,
            examId: 60,
            examStatus: 2,
            sessionStatus: 1,
            examEnrollmentStatus: $examEnrollmentStatus,
            academicEnrollmentStatus: 1,
            academicEnrollmentEffectiveTo: null,
            maxScore: '100',
        );
    }

    private function snapshot(): StudentGradeSnapshot
    {
        return new StudentGradeSnapshot(
            id: 11,
            academicYearId: 2026,
            schoolId: 1,
            examEnrollmentId: 10,
            examSessionId: 20,
            enrollmentId: 30,
            studentId: 40,
            subjectId: 50,
            score: '80',
            maxScore: '100',
            isAbsent: false,
            status: GradeStatus::Entered->value,
            isCurrent: true,
            correctionOfGradeId: null,
            enteredBy: 5,
            enteredAt: '2026-09-10T12:00:00+00:00',
            finalizedAt: null,
        );
    }
}
