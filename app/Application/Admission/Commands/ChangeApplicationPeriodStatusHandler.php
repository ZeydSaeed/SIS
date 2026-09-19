<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\ChangeApplicationPeriodStatusResult;
use App\Domain\Admission\Events\ApplicationPeriodStatusChanged;
use App\Domain\Admission\Exceptions\ApplicationPeriodNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;

final class ChangeApplicationPeriodStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeApplicationPeriodStatus';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeApplicationPeriodStatusResult
    {
        assert($command instanceof ChangeApplicationPeriodStatusCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ChangeApplicationPeriodStatusResult::fromIdempotency(
                    (int) $cached['period_id'],
                    (int) $cached['from_status'],
                    (int) $cached['to_status'],
                );
            }
        }

        $to = ApplicationPeriodStatus::tryFrom($command->status);
        if ($to === null) {
            throw new \DomainException('Application period status must be 0, 1, or 2.');
        }

        $period = $this->admission->findPeriodForSchool($command->periodId, $command->schoolId);
        if ($period === null || $period['academic_year_id'] !== $command->academicYearId) {
            throw ApplicationPeriodNotFoundException::forId($command->periodId);
        }

        $from = ApplicationPeriodStatus::from($period['status']);

        if ($from !== $to) {
            $this->unitOfWork->transaction(function () use ($command, $from, $to): void {
                $this->admission->updatePeriodStatus($command->periodId, $to->value);

                $this->outbox->stage(new ApplicationPeriodStatusChanged(
                    periodId: $command->periodId,
                    schoolId: $command->schoolId,
                    academicYearId: $command->academicYearId,
                    fromStatus: $from->value,
                    toStatus: $to->value,
                    occurredAt: new \DateTimeImmutable,
                ));
            });
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'period_id' => $command->periodId,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        }

        return ChangeApplicationPeriodStatusResult::success(
            $command->periodId,
            $from->value,
            $to->value,
        );
    }
}
