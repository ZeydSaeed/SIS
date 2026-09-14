<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\GradeLevelDTO;
use App\Domain\Academic\Repositories\GradeLevelRepositoryInterface;

final class ListGradeLevelsHandler
{
    public function __construct(
        private readonly GradeLevelRepositoryInterface $gradeLevels,
    ) {}

    /**
     * @return list<GradeLevelDTO>
     */
    public function handle(ListGradeLevelsQuery $query): array
    {
        return array_map(
            static fn ($row): GradeLevelDTO => new GradeLevelDTO(
                id: $row->id,
                code: $row->code,
                name: $row->name,
                levelOrder: $row->levelOrder,
                educationStage: $row->educationStage,
                status: $row->status,
            ),
            $this->gradeLevels->listAll(),
        );
    }
}
