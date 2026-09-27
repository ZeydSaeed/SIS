<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\UpdateApplicationFollowUpResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Data\UpdateApplicationFollowUpData;
use App\Domain\Admission\Events\ApplicationFollowUpUpdated;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\ApplicantDisplayNameParser;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use DomainException;

final class UpdateApplicationFollowUpHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateApplicationFollowUp';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ApplicantDisplayNameParser $nameParser,
    ) {}

    public function handle(Command $command): UpdateApplicationFollowUpResult
    {
        assert($command instanceof UpdateApplicationFollowUpCommand);

        if ($command->updates === []) {
            return UpdateApplicationFollowUpResult::failure(['No follow-up updates were provided.']);
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['application_ids'] ?? []);

                return UpdateApplicationFollowUpResult::fromIdempotency($cachedIds);
            }
        }

        $prepared = [];
        $applicationIds = [];

        foreach ($command->updates as $update) {
            $applicationId = (int) $update['application_id'];
            $application = $this->admission->findApplicationForSchool($applicationId, $command->schoolId);
            if ($application === null) {
                throw ApplicationNotFoundException::forId($applicationId);
            }

            $status = (int) $application['status'];
            $fullName = isset($update['full_name']) ? trim((string) $update['full_name']) : null;
            if ($fullName === null || $fullName === '') {
                $fullName = trim(implode(' ', array_filter([
                    (string) $application['first_name'],
                    $application['father_name'] ?? null,
                    $application['grandfather_name'] ?? null,
                    $application['great_grandfather_name'] ?? null,
                    (string) $application['last_name'],
                ], static fn (?string $part): bool => $part !== null && trim($part) !== '')));
            }

            $nameParts = $this->nameParser->parse($fullName);

            $updateRejection = (bool) ($update['update_rejection_reason'] ?? false);
            $updateWithdrawal = (bool) ($update['update_withdrawal_reason'] ?? false);

            if ($updateRejection && $status !== ApplicationStatus::Rejected->value) {
                throw new DomainException('Rejection reason can only be updated for rejected applications.');
            }

            if ($updateWithdrawal && $status !== ApplicationStatus::Withdrawn->value) {
                throw new DomainException('Withdrawal reason can only be updated for withdrawn applications.');
            }

            $rejectionReason = $updateRejection
                ? $this->nullableTrim($update['rejection_reason'] ?? null)
                : null;
            $withdrawalReason = $updateWithdrawal
                ? $this->nullableTrim($update['withdrawal_reason'] ?? null)
                : null;

            if ($updateRejection && ($rejectionReason === null || $rejectionReason === '')) {
                throw new DomainException('Rejection reason is required for rejected applications.');
            }

            if ($updateWithdrawal && ($withdrawalReason === null || $withdrawalReason === '')) {
                throw new DomainException('Withdrawal reason is required for withdrawn applications.');
            }

            $prepared[] = new UpdateApplicationFollowUpData(
                applicationId: $applicationId,
                firstName: $nameParts['first_name'],
                fatherName: $nameParts['father_name'],
                grandfatherName: $nameParts['grandfather_name'],
                greatGrandfatherName: $nameParts['great_grandfather_name'],
                lastName: $nameParts['last_name'],
                rejectionReason: $rejectionReason,
                withdrawalReason: $withdrawalReason,
                updateRejectionReason: $updateRejection,
                updateWithdrawalReason: $updateWithdrawal,
            );
            $applicationIds[] = $applicationId;
        }

        $this->unitOfWork->transaction(function () use ($command, $prepared): void {
            foreach ($prepared as $row) {
                $this->admission->updateFollowUp($row);
                $this->outbox->stage(new ApplicationFollowUpUpdated(
                    applicationId: $row->applicationId,
                    schoolId: $command->schoolId,
                    occurredAt: new \DateTimeImmutable,
                ));
            }
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'application_ids' => $applicationIds,
            ]);
        }

        return UpdateApplicationFollowUpResult::success($applicationIds);
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
