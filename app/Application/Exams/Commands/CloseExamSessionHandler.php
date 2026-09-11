<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CloseExamSessionResult;
use App\Domain\Exams\Events\ExamSessionClosed;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\ExamSessionLifecycleGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

final class CloseExamSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CloseExamSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CloseExamSessionResult
    {
        assert($command instanceof CloseExamSessionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForSession();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::CloseSession,
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

            ExamSessionLifecycleGuard::assertCanClose($session, $exam);

            $previousStatus = $session->status;
            $status = ExamSessionStatus::Completed->value;
            $this->exams->updateSessionAllowlisted($session->id, $command->schoolId, [
                'status' => $status,
            ]);

            $this->outbox->stage(new ExamSessionClosed(
                examSessionId: $session->id,
                examId: $session->examId,
                schoolId: $command->schoolId,
                previousStatus: $previousStatus,
                status: $status,
                closedBy: $command->actorUserId,
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
            return CloseExamSessionResult::fromIdempotency(
                (int) $payload['exam_session_id'],
                (int) $payload['exam_id'],
                (int) $payload['status'],
            );
        }

        return CloseExamSessionResult::success(
            (int) $payload['exam_session_id'],
            (int) $payload['exam_id'],
            (int) $payload['status'],
        );
    }
}
