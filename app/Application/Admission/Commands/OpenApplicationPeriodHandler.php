<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\OpenApplicationPeriodResult;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Events\ApplicationPeriodOpened;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use DomainException;

final class OpenApplicationPeriodHandler implements CommandHandler
{
    private const COMMAND_NAME = 'OpenApplicationPeriod';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): OpenApplicationPeriodResult
    {
        assert($command instanceof OpenApplicationPeriodCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return OpenApplicationPeriodResult::fromIdempotency((int) $cached['period_id']);
            }
        }

        if (strtotime($command->endDate) < strtotime($command->startDate)) {
            throw new DomainException('Application period end_date must be on or after start_date.');
        }

        if ($command->maxApplications !== null && $command->maxApplications <= 0) {
            throw new DomainException('max_applications must be null or greater than zero.');
        }

        $periodId = $this->unitOfWork->transaction(function () use ($command): int {
            $id = $this->admission->createPeriod(new CreateApplicationPeriodData(
                academicYearId: $command->academicYearId,
                schoolId: $command->schoolId,
                name: $command->name,
                startDate: $command->startDate,
                endDate: $command->endDate,
                maxApplications: $command->maxApplications,
                status: ApplicationPeriodStatus::Active->value,
            ));

            $this->outbox->stage(new ApplicationPeriodOpened(
                periodId: $id,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                name: $command->name,
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'period_id' => $periodId,
            ]);
        }

        return OpenApplicationPeriodResult::success($periodId);
    }
}
