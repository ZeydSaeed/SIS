<?php

namespace App\Application\Academic\Commands;

use App\Application\Academic\Results\CreateAcademicYearResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Database\StudentGradesPartitionManager;
use App\Domain\Academic\Events\AcademicYearCreated;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use DomainException;

final class CreateAcademicYearHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Academic.CreateAcademicYear';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AcademicYearRepositoryInterface $years,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateAcademicYearResult
    {
        assert($command instanceof CreateAcademicYearCommand);

        if ($command->idempotencyKey === null || trim($command->idempotencyKey) === '') {
            throw new DomainException('Idempotency key is required for CreateAcademicYear.');
        }

        if (trim($command->code) === '') {
            throw new DomainException('Academic year code is required.');
        }

        if ($command->endDate < $command->startDate) {
            throw new DomainException('Academic year end_date must be on or after start_date.');
        }

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateAcademicYearResult::fromIdempotency(
                (int) $cached['academic_year_id'],
                (string) $cached['code'],
            );
        }

        $existing = $this->years->findIdByCode($command->code);
        if ($existing !== null) {
            StudentGradesPartitionManager::ensurePartitionForAcademicYear($existing);

            return CreateAcademicYearResult::success($existing, $command->code);
        }

        $yearId = $this->unitOfWork->transaction(function () use ($command): int {
            $id = $this->years->insert(
                code: $command->code,
                name: $command->name,
                startDate: $command->startDate,
                endDate: $command->endDate,
                isCurrent: $command->isCurrent,
                status: $command->status,
            );

            $this->outbox->stage(new AcademicYearCreated(
                academicYearId: $id,
                code: $command->code,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'academic_year_id' => $id,
                'code' => $command->code,
            ]);

            return $id;
        });

        // DDL partition ensure after row commit (PostgreSQL CREATE TABLE PARTITION OF).
        StudentGradesPartitionManager::ensurePartitionForAcademicYear($yearId);

        return CreateAcademicYearResult::success($yearId, $command->code);
    }
}
