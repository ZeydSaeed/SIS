<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CancelExamSessionResult;
use App\Application\Exams\Support\CancelExamSessionMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class CancelExamSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CancelExamSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CancelExamSessionMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CancelExamSessionResult
    {
        assert($command instanceof CancelExamSessionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForSession();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::UpdateSession,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return CancelExamSessionResult::fromIdempotency(
                (int) $payload['exam_session_id'],
                (int) $payload['exam_id'],
                (int) $payload['status'],
                $payload['withdrawn_enrollment_ids'],
            );
        }

        return CancelExamSessionResult::success(
            (int) $payload['exam_session_id'],
            (int) $payload['exam_id'],
            (int) $payload['status'],
            $payload['withdrawn_enrollment_ids'],
        );
    }
}
