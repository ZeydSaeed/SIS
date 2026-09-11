<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\UpdateExamEnrollmentResult;
use App\Application\Exams\Support\UpdateExamEnrollmentMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class UpdateExamEnrollmentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.UpdateExamEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UpdateExamEnrollmentMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): UpdateExamEnrollmentResult
    {
        assert($command instanceof UpdateExamEnrollmentCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForEnrollment();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::UpdateEnrollment,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
            'enrollment_id' => $command->enrollmentId,
            'target_status' => $command->status ?? '__omit__',
            'seat_number' => $command->seatNumberProvided ? $command->seatNumber : '__omit__',
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return UpdateExamEnrollmentResult::fromIdempotency(
                (int) $payload['exam_enrollment_id'],
                (int) $payload['exam_session_id'],
                (int) $payload['enrollment_id'],
                (int) $payload['status'],
            );
        }

        return UpdateExamEnrollmentResult::success(
            (int) $payload['exam_enrollment_id'],
            (int) $payload['exam_session_id'],
            (int) $payload['enrollment_id'],
            (int) $payload['status'],
        );
    }
}
