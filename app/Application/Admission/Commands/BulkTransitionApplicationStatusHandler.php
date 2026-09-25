<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\BulkTransitionApplicationStatusResult;
use App\Application\Admission\Support\AcceptedApplicationStudentConverter;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Events\ApplicationStatusTransitioned;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\BulkApplicationTransitionGuard;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

final class BulkTransitionApplicationStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'BulkTransitionApplicationStatus';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly BulkApplicationTransitionGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly AcceptedApplicationStudentConverter $convertAccepted,
    ) {}

    public function handle(Command $command): BulkTransitionApplicationStatusResult
    {
        assert($command instanceof BulkTransitionApplicationStatusCommand);

        $applicationIds = $this->guard->uniqueIds($command->applicationIds);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['application_ids'] ?? []);

                return BulkTransitionApplicationStatusResult::fromIdempotency(
                    $cachedIds,
                    (int) $cached['to_status'],
                );
            }
        }

        $to = $this->guard->requireManualTarget($command->toStatus);
        $prepared = $this->guard->prepare($applicationIds, $command->schoolId, $to);

        $this->unitOfWork->transaction(function () use ($command, $to, $prepared): void {
            foreach ($prepared as $item) {
                $this->admission->transitionApplicationStatus(
                    $item['id'],
                    $to->value,
                    $command->reviewedBy,
                    $command->notes,
                );
                $this->outbox->stage(new ApplicationStatusTransitioned(
                    applicationId: $item['id'],
                    schoolId: $command->schoolId,
                    fromStatus: $item['from']->value,
                    toStatus: $to->value,
                    reviewedBy: $command->reviewedBy,
                    occurredAt: new \DateTimeImmutable,
                ));
            }
        });

        if ($to === ApplicationStatus::Accepted) {
            $this->convertAccepted->repairOrphans($command->schoolId, $command->reviewedBy);

            $fromById = [];
            foreach ($prepared as $item) {
                $fromById[$item['id']] = $item['from'];
            }

            foreach ($applicationIds as $applicationId) {
                try {
                    $this->convertAccepted->convert(
                        schoolId: $command->schoolId,
                        applicationId: $applicationId,
                        reviewedBy: $command->reviewedBy,
                        idempotencyKey: $command->idempotencyKey !== null
                            ? $command->idempotencyKey.':convert:'.$applicationId
                            : null,
                    );
                } catch (\Throwable $exception) {
                    $from = $fromById[$applicationId] ?? null;
                    if ($from instanceof ApplicationStatus) {
                        $this->admission->transitionApplicationStatus(
                            $applicationId,
                            $from->value,
                            $command->reviewedBy,
                            $command->notes,
                        );
                    }

                    throw $exception;
                }
            }
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_ids' => $applicationIds,
                'to_status' => $to->value,
            ]);
        }

        return BulkTransitionApplicationStatusResult::success($applicationIds, $to->value);
    }
}
