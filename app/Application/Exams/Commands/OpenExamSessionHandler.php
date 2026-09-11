<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\OpenExamSessionResult;
use App\Domain\Exams\Events\ExamSessionOpened;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\ExamSessionLifecycleGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

final class OpenExamSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.OpenExamSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): OpenExamSessionResult
    {
        assert($command instanceof OpenExamSessionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForSession();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::OpenSession,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_session_id' => $command->examSessionId,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'exam_session_id' => (int) $cached['exam_session_id'],
                    'exam_id' => (int) $cached['exam_id'],
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

            ExamSessionLifecycleGuard::assertCanOpen($session, $exam);

            $previousStatus = $session->status;
            $status = ExamSessionStatus::InProgress->value;
            $this->exams->updateSessionAllowlisted($session->id, $command->schoolId, [
                'status' => $status,
            ]);

            $this->outbox->stage(new ExamSessionOpened(
                examSessionId: $session->id,
                examId: $session->examId,
                schoolId: $command->schoolId,
                previousStatus: $previousStatus,
                status: $status,
                openedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_session_id' => $session->id,
                'exam_id' => $session->examId,
                'status' => $status,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return OpenExamSessionResult::fromIdempotency(
                (int) $payload['exam_session_id'],
                (int) $payload['exam_id'],
                (int) $payload['status'],
            );
        }

        return OpenExamSessionResult::success(
            (int) $payload['exam_session_id'],
            (int) $payload['exam_id'],
            (int) $payload['status'],
        );
    }
}
