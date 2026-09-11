<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CreateExamEnrollmentResult;
use App\Application\Exams\Support\CreateExamEnrollmentMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class CreateExamEnrollmentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CreateExamEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CreateExamEnrollmentMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CreateExamEnrollmentResult
    {
        assert($command instanceof CreateExamEnrollmentCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForEnrollment();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::CreateEnrollment,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
            'enrollment_id' => $command->enrollmentId,
            'seat_number' => $command->seatNumber,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return CreateExamEnrollmentResult::fromIdempotency(
                (int) $payload['exam_enrollment_id'],
                (int) $payload['exam_session_id'],
                (int) $payload['enrollment_id'],
                (int) $payload['status'],
            );
        }

        return CreateExamEnrollmentResult::success(
            (int) $payload['exam_enrollment_id'],
            (int) $payload['exam_session_id'],
            (int) $payload['enrollment_id'],
            (int) $payload['status'],
        );
    }
}
