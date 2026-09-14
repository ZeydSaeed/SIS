<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\TermDTO;
use App\Domain\Academic\Repositories\TermRepositoryInterface;

final class ListTermsHandler
{
    public function __construct(
        private readonly TermRepositoryInterface $terms,
    ) {}

    /**
     * @return list<TermDTO>
     */
    public function handle(ListTermsQuery $query): array
    {
        return array_map(
            static fn ($row): TermDTO => new TermDTO(
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
            ),
            $this->terms->listAll($query->academicYearId),
        );
    }
}
