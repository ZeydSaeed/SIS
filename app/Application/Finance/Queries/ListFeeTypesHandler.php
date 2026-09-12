<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\FeeTypeDTO;
use App\Domain\Finance\Repositories\FeeTypeRepositoryInterface;

final class ListFeeTypesHandler
{
    public function __construct(
        private readonly FeeTypeRepositoryInterface $feeTypes,
    ) {}

    /**
     * @return list<FeeTypeDTO>
     */
    public function handle(ListFeeTypesQuery $query): array
    {
        return array_map(
            static fn ($s): FeeTypeDTO => new FeeTypeDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                code: $s->code,
                name: $s->name,
                amount: $s->amount,
                isRecurring: $s->isRecurring,
                status: $s->status,
                createdAt: $s->createdAt,
            ),
            $this->feeTypes->listBySchool($query->schoolId, $query->status),
        );
    }
}
