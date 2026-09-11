<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CancelExamEnrollmentResult;
use App\Application\Exams\Support\CancelExamEnrollmentMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class CancelExamEnrollmentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CancelExamEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CancelExamEnrollmentMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CancelExamEnrollmentResult
    {
        assert($command instanceof CancelExamEnrollmentCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForEnrollment();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::CancelEnrollment,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
            'enrollment_id' => $command->enrollmentId,
            'exam_enrollment_id' => $command->examEnrollmentId,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return CancelExamEnrollmentResult::fromIdempotency(
                (int) $payload['exam_enrollment_id'],
                (int) $payload['exam_session_id'],
                (int) $payload['enrollment_id'],
                (int) $payload['status'],
                (bool) ($payload['noop'] ?? false),
            );
        }

        return CancelExamEnrollmentResult::success(
            (int) $payload['exam_enrollment_id'],
            (int) $payload['exam_session_id'],
            (int) $payload['enrollment_id'],
            (int) $payload['status'],
            (bool) ($payload['_noop'] ?? false),
        );
    }
}
