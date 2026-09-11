<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\CreateExamEnrollmentCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentHandler;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Exams\Data\CreateExamEnrollmentData;
use App\Domain\Exams\Events\ExamEnrollmentCreated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\CreateExamEnrollmentGuard;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;

/**
 * Keeps CreateExamEnrollmentHandler under architecture complexity limits.
 */
final class CreateExamEnrollmentMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly EnrollmentRepositoryInterface $academicEnrollments,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(CreateExamEnrollmentCommand $command, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, CreateExamEnrollmentHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_enrollment_id' => (int) $cached['exam_enrollment_id'],
                'exam_session_id' => (int) $cached['exam_session_id'],
                'enrollment_id' => (int) $cached['enrollment_id'],
                'status' => (int) $cached['status'],
            ];
        }

        $session = $this->exams->lockSessionByIdAndSchool($command->examSessionId, $command->schoolId);
        if ($session === null) {
            throw ExamValidationException::sessionNotFound();
        }

        $exam = $this->exams->findByIdAndSchool($session->examId, $command->schoolId);
        if ($exam === null) {
            throw ExamValidationException::parentExamNotFound();
        }

        $academic = $this->academicEnrollments->findByIdAndSchool($command->enrollmentId, $command->schoolId);
        if ($academic === null) {
            throw ExamValidationException::academicEnrollmentNotFound();
        }

        CreateExamEnrollmentGuard::assertReady(
            session: $session,
            exam: $exam,
            enrollmentId: $academic->id,
            enrollmentSchoolId: $academic->schoolId,
            enrollmentAcademicYearId: $academic->academicYearId,
            enrollmentActive: $academic->isActive(),
            seatAlreadyExists: $this->exams->examEnrollmentSeatExists(
                $session->id,
                $academic->id,
                $command->schoolId,
            ),
        );

        $status = ExamEnrollmentStatus::Registered->value;
        $examEnrollmentId = $this->exams->insertExamEnrollment(new CreateExamEnrollmentData(
            examSessionId: $session->id,
            schoolId: $command->schoolId,
            enrollmentId: $academic->id,
            status: $status,
            seatNumber: $command->seatNumber,
        ));

        $this->outbox->stage(new ExamEnrollmentCreated(
            examEnrollmentId: $examEnrollmentId,
            examSessionId: $session->id,
            examId: $session->examId,
            schoolId: $command->schoolId,
            enrollmentId: $academic->id,
            status: $status,
            seatNumber: $command->seatNumber,
            createdBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_enrollment_id' => $examEnrollmentId,
            'exam_session_id' => $session->id,
            'enrollment_id' => $academic->id,
            'status' => $status,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, CreateExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false];
    }
}
