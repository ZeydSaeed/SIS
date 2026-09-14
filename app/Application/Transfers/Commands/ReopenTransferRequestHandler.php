<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Transfers\Results\ReopenTransferRequestResult;
use App\Domain\Transfers\Events\TransferRequestReopened;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\Support\TransferIdempotencyGuard;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;

final class ReopenTransferRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReopenTransferRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TransferRepositoryInterface $transfers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReopenTransferRequestResult
    {
        assert($command instanceof ReopenTransferRequestCommand);
        $key = TransferIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReopenTransferRequestResult::fromIdempotency((int) $cached['transfer_request_id']);
        }

        $req = $this->transfers->findRequestForSchool($command->transferRequestId, $command->schoolId);
        if ($req === null) {
            return ReopenTransferRequestResult::failure(['transfers.request_not_found']);
        }
        if ($req->status === TransferRequestStatus::Pending) {
            return ReopenTransferRequestResult::failure(['transfers.request_already_open']);
        }
        if ($req->status !== TransferRequestStatus::Cancelled) {
            return ReopenTransferRequestResult::failure(['transfers.request_not_reopenable']);
        }

        $previous = $req->status;

        $ok = $this->unitOfWork->transaction(function () use ($command, $key, $req, $previous): bool {
            if (! $this->transfers->markReopened($command->transferRequestId, $command->schoolId)) {
                return false;
            }

            $this->outbox->stage(new TransferRequestReopened(
                $req->id,
                $req->fromSchoolId,
                $req->toSchoolId,
                $previous,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'transfer_request_id' => $command->transferRequestId,
            ]);

            return true;
        });

        if (! $ok) {
            return ReopenTransferRequestResult::failure(['transfers.reopen_failed']);
        }

        return ReopenTransferRequestResult::success($command->transferRequestId);
    }
}
