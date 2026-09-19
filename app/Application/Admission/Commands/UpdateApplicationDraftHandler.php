<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\UpdateApplicationDraftResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Events\ApplicationDraftUpdated;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use DomainException;

final class UpdateApplicationDraftHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateApplicationDraft';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateApplicationDraftResult
    {
        assert($command instanceof UpdateApplicationDraftCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return UpdateApplicationDraftResult::fromIdempotency((int) $cached['application_id']);
            }
        }

        $application = $this->admission->findApplicationForSchool($command->applicationId, $command->schoolId);
        if ($application === null) {
            throw ApplicationNotFoundException::forId($command->applicationId);
        }

        if ((int) $application['status'] === ApplicationStatus::Converted->value
            || (int) $application['status'] === ApplicationStatus::Rejected->value
            || (int) $application['status'] === ApplicationStatus::Withdrawn->value) {
            throw new DomainException('Terminal applications cannot be updated from the stage workspace.');
        }

        $this->unitOfWork->transaction(function () use ($command): void {
            $this->admission->updateDraft(new UpdateApplicationDraftData(
                applicationId: $command->applicationId,
                notes: $command->notes,
                reviewedAt: $command->reviewedAt,
            ));

            $this->outbox->stage(new ApplicationDraftUpdated(
                applicationId: $command->applicationId,
                schoolId: $command->schoolId,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $command->applicationId,
            ]);
        }

        return UpdateApplicationDraftResult::success($command->applicationId);
    }
}
