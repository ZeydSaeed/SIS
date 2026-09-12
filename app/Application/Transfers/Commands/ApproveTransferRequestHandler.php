<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Transfers\Results\ApproveTransferRequestResult;
use App\Domain\Transfers\Events\TransferRequestApproved;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\Support\TransferIdempotencyGuard;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;

final class ApproveTransferRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ApproveTransferRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TransferRepositoryInterface $transfers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ApproveTransferRequestResult
    {
        assert($command instanceof ApproveTransferRequestCommand);
        $key = TransferIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ApproveTransferRequestResult::fromIdempotency((int) $cached['transfer_request_id']);
        }

        $req = $this->transfers->findRequestForSchool($command->transferRequestId, $command->toSchoolId);
        if ($req === null || $req->toSchoolId !== $command->toSchoolId) {
            return ApproveTransferRequestResult::failure(['transfers.request_not_found']);
        }
        if ($req->status !== TransferRequestStatus::Pending) {
            return ApproveTransferRequestResult::failure(['transfers.not_pending']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $ok = $this->unitOfWork->transaction(function () use ($command, $key, $req, $at): bool {
            $updated = $this->transfers->markApproved(
                $command->transferRequestId,
                $command->toSchoolId,
                $command->approvedBy,
                $at,
            );
            if (! $updated) {
                return false;
            }
            $this->outbox->stage(new TransferRequestApproved(
                $req->id,
                $req->fromSchoolId,
                $req->toSchoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'transfer_request_id' => $command->transferRequestId,
            ]);

            return true;
        });

        if (! $ok) {
            return ApproveTransferRequestResult::failure(['transfers.approve_failed']);
        }

        return ApproveTransferRequestResult::success($command->transferRequestId);
    }
}
