<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\ReactivateFeeTypeResult;
use App\Domain\Finance\Events\FeeTypeReactivated;
use App\Domain\Finance\Repositories\FeeTypeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\ValueObjects\FeeTypeStatus;

final class ReactivateFeeTypeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateFeeType';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly FeeTypeRepositoryInterface $feeTypes,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateFeeTypeResult
    {
        assert($command instanceof ReactivateFeeTypeCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateFeeTypeResult::fromIdempotency((int) $cached['fee_type_id']);
        }

        $row = $this->feeTypes->find($command->schoolId, $command->feeTypeId);
        if ($row === null) {
            return ReactivateFeeTypeResult::failure(['finance.fee_type_not_found']);
        }
        if ($row->status === FeeTypeStatus::Active) {
            return ReactivateFeeTypeResult::failure(['finance.fee_type_already_active']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->feeTypes->setStatus($command->schoolId, $command->feeTypeId, FeeTypeStatus::Active);
            $this->outbox->stage(new FeeTypeReactivated(
                $command->feeTypeId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['fee_type_id' => $command->feeTypeId]);
        });

        return ReactivateFeeTypeResult::success($command->feeTypeId);
    }
}
