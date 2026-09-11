<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\UpdateExamResult;
use App\Application\Exams\Support\UpdateExamMutationService;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;

final class UpdateExamHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.UpdateExam';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UpdateExamMutationService $mutation,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): UpdateExamResult
    {
        assert($command instanceof UpdateExamCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKey();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::Update,
            $command->actorUserId,
            $command->schoolId,
        );

        $metadata = $this->mutation->metadataFields($command);
        if ($metadata === [] && $command->targetStatus === null) {
            throw ExamValidationException::emptyUpdate();
        }

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_id' => $command->examId,
            'name' => $command->name,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
            'exam_type_id' => $command->examTypeId,
            'term_id' => $command->termId,
            'target_status' => $command->targetStatus,
        ]);

        $payload = $this->unitOfWork->transaction(
            fn (): array => $this->mutation->execute($command, $metadata, $fingerprint),
        );

        if ($payload['_replay'] === true) {
            return UpdateExamResult::fromIdempotency(
                (int) $payload['exam_id'],
                (int) $payload['status'],
            );
        }

        return UpdateExamResult::success(
            (int) $payload['exam_id'],
            (int) $payload['status'],
        );
    }
}
