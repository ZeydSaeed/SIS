<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\TermDTO;
use App\Domain\Academic\Repositories\TermRepositoryInterface;

final class GetTermHandler
{
    public function __construct(
        private readonly TermRepositoryInterface $terms,
    ) {}

    public function handle(GetTermQuery $query): ?TermDTO
    {
        $row = $this->terms->findById($query->termId);
        if ($row === null) {
            return null;
        }

        return new TermDTO(
            id: $row->id,
            academicYearId: $row->academicYearId,
            code: $row->code,
            name: $row->name,
            startDate: $row->startDate,
            endDate: $row->endDate,
            termOrder: $row->termOrder,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
