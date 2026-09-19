<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\UpdateApplicationPeriodResult;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;
use App\Domain\Admission\Events\ApplicationPeriodUpdated;
use App\Domain\Admission\Exceptions\ApplicationPeriodNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use DomainException;

final class UpdateApplicationPeriodHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateApplicationPeriod';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateApplicationPeriodResult
    {
        assert($command instanceof UpdateApplicationPeriodCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return UpdateApplicationPeriodResult::fromIdempotency((int) $cached['period_id']);
            }
        }

        if (strtotime($command->endDate) < strtotime($command->startDate)) {
            throw new DomainException('Application period end_date must be on or after start_date.');
        }

        if ($command->maxApplications !== null && $command->maxApplications <= 0) {
            throw new DomainException('max_applications must be null or greater than zero.');
        }

        $period = $this->admission->findPeriodForSchool($command->periodId, $command->schoolId);
        if ($period === null || $period['academic_year_id'] !== $command->academicYearId) {
            throw ApplicationPeriodNotFoundException::forId($command->periodId);
        }

        $this->unitOfWork->transaction(function () use ($command): void {
            $this->admission->updatePeriod(new UpdateApplicationPeriodData(
                periodId: $command->periodId,
                name: $command->name,
                startDate: $command->startDate,
                endDate: $command->endDate,
                maxApplications: $command->maxApplications,
            ));

            $this->outbox->stage(new ApplicationPeriodUpdated(
                periodId: $command->periodId,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                name: $command->name,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'period_id' => $command->periodId,
            ]);
        }

        return UpdateApplicationPeriodResult::success($command->periodId);
    }
}
