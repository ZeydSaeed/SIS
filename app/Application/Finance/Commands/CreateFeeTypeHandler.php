<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\CreateFeeTypeResult;
use App\Domain\Finance\Events\FeeTypeCreated;
use App\Domain\Finance\Repositories\FeeTypeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\ValueObjects\FeeTypeStatus;

final class CreateFeeTypeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateFeeType';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly FeeTypeRepositoryInterface $feeTypes,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateFeeTypeResult
    {
        assert($command instanceof CreateFeeTypeCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateFeeTypeResult::fromIdempotency((int) $cached['fee_type_id']);
        }

        $code = strtoupper(trim($command->code));
        if ($code === '' || strlen($code) > 20) {
            return CreateFeeTypeResult::failure(['finance.fee_type_code_invalid']);
        }
        if (trim($command->name) === '') {
            return CreateFeeTypeResult::failure(['finance.fee_type_name_invalid']);
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $command->amount) || bccomp($command->amount, '0', 2) < 0) {
            return CreateFeeTypeResult::failure(['finance.fee_type_amount_invalid']);
        }
        if ($this->feeTypes->findIdBySchoolAndCode($command->schoolId, $code) !== null) {
            return CreateFeeTypeResult::failure(['finance.fee_type_code_duplicate']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $code, $at): int {
            $id = $this->feeTypes->create(
                $command->schoolId,
                $code,
                trim($command->name),
                $command->amount,
                $command->isRecurring,
                FeeTypeStatus::Active,
                $at,
            );
            $this->outbox->stage(new FeeTypeCreated(
                $id,
                $command->schoolId,
                $code,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['fee_type_id' => $id]);

            return $id;
        });

        return CreateFeeTypeResult::success($id);
    }
}
