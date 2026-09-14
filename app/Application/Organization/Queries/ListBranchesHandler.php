<?php

namespace App\Application\Organization\Queries;

use App\Application\Organization\DTOs\BranchDTO;
use App\Domain\Organization\Repositories\BranchRepositoryInterface;

final class ListBranchesHandler
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
    ) {}

    /**
     * @return list<BranchDTO>
     */
    public function handle(ListBranchesQuery $query): array
    {
        return array_map(
            static fn ($row): BranchDTO => new BranchDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                code: $row->code,
                name: $row->name,
                address: $row->address,
                status: $row->status,
                createdAt: $row->createdAt,
                updatedAt: $row->updatedAt,
            ),
            $this->branches->listForSchool($query->schoolId),
        );
    }
}
