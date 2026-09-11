<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CreateExamSessionResult;
use App\Domain\Exams\Data\CreateExamSessionData;
use App\Domain\Exams\Events\ExamSessionCreated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Services\CreateExamSessionGuard;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

final class CreateExamSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CreateExamSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
        private readonly CreateExamSessionGuard $sessionGuard,
    ) {}

    public function handle(Command $command): CreateExamSessionResult
    {
        assert($command instanceof CreateExamSessionCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKeyForSession();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::CreateSession,
            $command->actorUserId,
            $command->schoolId,
        );

        $this->sessionGuard->assertReady(
            schoolId: $command->schoolId,
            examId: $command->examId,
            subjectId: $command->subjectId,
            startTime: $command->startTime,
            endTime: $command->endTime,
            maxGrade: $command->maxGrade,
            passGrade: $command->passGrade,
            roomId: $command->roomId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_id' => $command->examId,
            'subject_id' => $command->subjectId,
            'session_date' => $command->sessionDate,
            'start_time' => $command->startTime,
            'end_time' => $command->endTime,
            'room_id' => $command->roomId,
            'max_grade' => $command->maxGrade,
            'pass_grade' => $command->passGrade,
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

            $status = ExamSessionStatus::Scheduled->value;
            $sessionId = $this->exams->insertSession(new CreateExamSessionData(
                examId: $command->examId,
                schoolId: $command->schoolId,
                subjectId: $command->subjectId,
                sessionDate: $command->sessionDate,
                startTime: $command->startTime,
                endTime: $command->endTime,
                roomId: $command->roomId,
                maxGrade: $command->maxGrade,
                passGrade: $command->passGrade,
                status: $status,
            ));

            $this->outbox->stage(new ExamSessionCreated(
                examSessionId: $sessionId,
                examId: $command->examId,
                schoolId: $command->schoolId,
                subjectId: $command->subjectId,
                sessionDate: $command->sessionDate,
                startTime: $command->startTime,
                endTime: $command->endTime,
                roomId: $command->roomId,
                maxGrade: $command->maxGrade,
                passGrade: $command->passGrade,
                status: $status,
                createdBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_session_id' => $sessionId,
                'exam_id' => $command->examId,
                'status' => $status,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return CreateExamSessionResult::fromIdempotency(
                (int) $payload['exam_session_id'],
                (int) $payload['exam_id'],
                (int) $payload['status'],
            );
        }

        return CreateExamSessionResult::success(
            (int) $payload['exam_session_id'],
            (int) $payload['exam_id'],
            (int) $payload['status'],
        );
    }
}
