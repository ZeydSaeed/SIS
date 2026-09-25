<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\TransitionApplicationStatusResult;
use App\Application\Admission\Support\AcceptedApplicationStudentConverter;
use App\Domain\Admission\Events\ApplicationStatusTransitioned;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Exceptions\InvalidApplicationTransitionException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

final class TransitionApplicationStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'TransitionApplicationStatus';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly AcceptedApplicationStudentConverter $convertAccepted,
    ) {}

    public function handle(Command $command): TransitionApplicationStatusResult
    {
        assert($command instanceof TransitionApplicationStatusCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return TransitionApplicationStatusResult::fromIdempotency(
                    (int) $cached['application_id'],
                    (int) $cached['from_status'],
                    (int) $cached['to_status'],
                );
            }
        }

        $application = $this->admission->findApplicationForSchool($command->applicationId, $command->schoolId);
        if ($application === null) {
            throw ApplicationNotFoundException::forId($command->applicationId);
        }

        $from = ApplicationStatus::from($application['status']);
        $to = ApplicationStatus::tryFrom($command->toStatus);
        if ($to === null || ! $from->canTransitionTo($to)) {
            throw InvalidApplicationTransitionException::fromTo($application['status'], $command->toStatus);
        }

        if ($to === ApplicationStatus::Converted) {
            throw InvalidApplicationTransitionException::fromTo($application['status'], $command->toStatus);
        }

        $this->unitOfWork->transaction(function () use ($command, $from, $to): void {
            $this->admission->transitionApplicationStatus(
                $command->applicationId,
                $to->value,
                $command->reviewedBy,
                $command->notes,
            );

            $this->outbox->stage(new ApplicationStatusTransitioned(
                applicationId: $command->applicationId,
                schoolId: $command->schoolId,
                fromStatus: $from->value,
                toStatus: $to->value,
                reviewedBy: $command->reviewedBy,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($to === ApplicationStatus::Accepted) {
            $this->convertAccepted->repairOrphans($command->schoolId, $command->reviewedBy);

            try {
                $this->convertAccepted->convert(
                    schoolId: $command->schoolId,
                    applicationId: $command->applicationId,
                    reviewedBy: $command->reviewedBy,
                    idempotencyKey: $command->idempotencyKey !== null
                        ? $command->idempotencyKey.':convert'
                        : null,
                );
            } catch (\Throwable $exception) {
                $this->admission->transitionApplicationStatus(
                    $command->applicationId,
                    $from->value,
                    $command->reviewedBy,
                    $command->notes,
                );

                throw $exception;
            }
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_id' => $command->applicationId,
                'from_status' => $from->value,
                'to_status' => $to->value,
            ]);
        }

        return TransitionApplicationStatusResult::success(
            $command->applicationId,
            $from->value,
            $to->value,
        );
    }
}
