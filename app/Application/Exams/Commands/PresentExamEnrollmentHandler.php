<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\PresentExamEnrollmentResult;
use App\Application\Exams\Support\PresentExamEnrollmentMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class PresentExamEnrollmentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.PresentExamEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PresentExamEnrollmentMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): PresentExamEnrollmentResult
    {
        assert($command instanceof PresentExamEnrollmentCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForEnrollment();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::PresentEnrollment,
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
            return PresentExamEnrollmentResult::fromIdempotency(
                (int) $payload['exam_enrollment_id'],
                (int) $payload['exam_session_id'],
                (int) $payload['enrollment_id'],
                (int) $payload['status'],
                (bool) ($payload['noop'] ?? false),
            );
        }

        return PresentExamEnrollmentResult::success(
            (int) $payload['exam_enrollment_id'],
            (int) $payload['exam_session_id'],
            (int) $payload['enrollment_id'],
            (int) $payload['status'],
            (bool) ($payload['_noop'] ?? false),
        );
    }
}
