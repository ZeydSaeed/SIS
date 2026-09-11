<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CreateExamResult;
use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Events\ExamCreated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamStatus;

final class CreateExamHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CreateExam';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CreateExamResult
    {
        assert($command instanceof CreateExamCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKey();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::Create,
            $command->actorUserId,
            $command->schoolId,
        );

        if ($command->endDate < $command->startDate) {
            throw ExamValidationException::invalidDates();
        }

        if (! $this->exams->academicYearExists($command->academicYearId)) {
            throw ExamValidationException::missingAcademicYear();
        }

        if (! $this->exams->termBelongsToAcademicYear($command->termId, $command->academicYearId)) {
            throw ExamValidationException::missingTerm();
        }

        if (! $this->exams->examTypeExists($command->examTypeId)) {
            throw ExamValidationException::missingExamType();
        }

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'academic_year_id' => $command->academicYearId,
            'term_id' => $command->termId,
            'exam_type_id' => $command->examTypeId,
            'name' => $command->name,
            'start_date' => $command->startDate,
            'end_date' => $command->endDate,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'exam_id' => (int) $cached['exam_id'],
                    'academic_year_id' => (int) $cached['academic_year_id'],
                    'status' => (int) $cached['status'],
                ];
            }

            $status = ExamStatus::Draft->value;
            $examId = $this->exams->insert(new CreateExamData(
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                termId: $command->termId,
                examTypeId: $command->examTypeId,
                name: $command->name,
                startDate: $command->startDate,
                endDate: $command->endDate,
                status: $status,
            ));

            $this->outbox->stage(new ExamCreated(
                examId: $examId,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                termId: $command->termId,
                examTypeId: $command->examTypeId,
                name: $command->name,
                startDate: $command->startDate,
                endDate: $command->endDate,
                status: $status,
                createdBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_id' => $examId,
                'academic_year_id' => $command->academicYearId,
                'status' => $status,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return CreateExamResult::fromIdempotency(
                (int) $payload['exam_id'],
                (int) $payload['academic_year_id'],
                (int) $payload['status'],
            );
        }

        return CreateExamResult::success(
            (int) $payload['exam_id'],
            (int) $payload['academic_year_id'],
            (int) $payload['status'],
        );
    }
}
