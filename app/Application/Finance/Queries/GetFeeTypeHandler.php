<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\FeeTypeDTO;
use App\Domain\Finance\Repositories\FeeTypeRepositoryInterface;

final class GetFeeTypeHandler
{
    public function __construct(
        private readonly FeeTypeRepositoryInterface $feeTypes,
    ) {}

    public function handle(GetFeeTypeQuery $query): ?FeeTypeDTO
    {
        $s = $this->feeTypes->find($query->schoolId, $query->feeTypeId);
        if ($s === null) {
            return null;
        }

        return new FeeTypeDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            code: $s->code,
            name: $s->name,
            amount: $s->amount,
            isRecurring: $s->isRecurring,
            status: $s->status,
            createdAt: $s->createdAt,
        );
    }
}
