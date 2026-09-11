<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\UpdateExamSessionResult;
use App\Application\Exams\Support\UpdateExamSessionMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class UpdateExamSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.UpdateExamSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UpdateExamSessionMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): UpdateExamSessionResult
    {
        assert($command instanceof UpdateExamSessionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForSession();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::UpdateSession,
            $command->actorUserId,
            $command->schoolId,
        );

        $metadata = $this->mutation->metadataFields($command);
        if ($metadata === []) {
            throw ExamValidationException::emptySessionUpdate();
        }

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
            'session_date' => $command->sessionDate,
            'start_time' => $command->startTime,
            'end_time' => $command->endTime,
            'room_id' => $command->roomIdProvided ? $command->roomId : '__omit__',
            'max_grade' => $command->maxGrade,
            'pass_grade' => $command->passGrade,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $metadata, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return UpdateExamSessionResult::fromIdempotency(
                (int) $payload['exam_session_id'],
                (int) $payload['exam_id'],
                (int) $payload['status'],
            );
        }

        return UpdateExamSessionResult::success(
            (int) $payload['exam_session_id'],
            (int) $payload['exam_id'],
            (int) $payload['status'],
        );
    }
}
