<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Contracts\ExamSeatingReadRepositoryInterface;
use App\Application\Exams\Results\SeatClassInExamSessionResult;
use App\Application\Exams\Support\ExamSeatingTargetResolver;
use App\Domain\Exams\Exceptions\ExamSeatingException;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use DomainException;

/**
 * Seats a class (or one section) into an exam session through the single-seat command, one transaction per student,
 * with a per-student idempotency key derived from the request key — a repeated request replays, never duplicates.
 * Students without the session's subject assigned are reported, not seated.
 */
final class SeatClassInExamSessionHandler implements CommandHandler
{
    public function __construct(
        private readonly ExamAdministrationAuthorityPort $authority,
        private readonly ExamSeatingTargetResolver $target,
        private readonly ExamSeatingReadRepositoryInterface $seating,
        private readonly CreateExamEnrollmentHandler $seat,
    ) {}

    public function handle(Command $command): SeatClassInExamSessionResult
    {
        assert($command instanceof SeatClassInExamSessionCommand);

        $this->authority->assertCan(ExamAdministrationAction::CreateEnrollment, $command->actorUserId, $command->schoolId);
        $key = trim((string) $command->idempotencyKey);
        if ($key === '') {
            throw ExamSeatingException::missingIdempotencyKey();
        }
        $target = $this->target->resolve($command);

        $outcomes = [];
        foreach ($this->seating->candidates($command->schoolId, $target['academic_year_id'], $command->classId, $command->sectionId, $command->examSessionId, $target['subject_id']) as $candidate) {
            $outcomes[] = match (true) {
                ! $candidate['takes_subject'] => $this->outcome($candidate['enrollment_id'], 'subject_not_assigned'),
                $candidate['seated'] => $this->outcome($candidate['enrollment_id'], 'already_seated'),
                default => $this->seatOne($command, $candidate['enrollment_id'], $key),
            };
        }

        return SeatClassInExamSessionResult::fromOutcomes($outcomes);
    }

    /**
     * @return array{enrollment_id: int, status: string, exam_enrollment_id: ?int}
     */
    private function seatOne(SeatClassInExamSessionCommand $command, int $enrollmentId, string $key): array
    {
        try {
            $result = $this->seat->handle(new CreateExamEnrollmentCommand(
                schoolId: $command->schoolId,
                examSessionId: $command->examSessionId,
                enrollmentId: $enrollmentId,
                actorUserId: $command->actorUserId,
                idempotencyKey: 'seating:'.hash('sha256', $key.'|'.$enrollmentId),
            ));
        } catch (DomainException) {
            return $this->outcome($enrollmentId, 'failed');
        }

        return $this->outcome($enrollmentId, $result->fromIdempotencyCache ? 'replayed' : 'seated', $result->examEnrollmentId);
    }

    /**
     * @return array{enrollment_id: int, status: string, exam_enrollment_id: ?int}
     */
    private function outcome(int $enrollmentId, string $status, ?int $examEnrollmentId = null): array
    {
        return ['enrollment_id' => $enrollmentId, 'status' => $status, 'exam_enrollment_id' => $examEnrollmentId];
    }
}
