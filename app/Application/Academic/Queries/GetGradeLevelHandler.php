<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\GradeLevelDTO;
use App\Domain\Academic\Repositories\GradeLevelRepositoryInterface;

final class GetGradeLevelHandler
{
    public function __construct(
        private readonly GradeLevelRepositoryInterface $gradeLevels,
    ) {}

    public function handle(GetGradeLevelQuery $query): ?GradeLevelDTO
    {
        $row = $this->gradeLevels->findById($query->gradeLevelId);
        if ($row === null) {
            return null;
        }

        return new GradeLevelDTO(
            id: $row->id,
            code: $row->code,
            name: $row->name,
            levelOrder: $row->levelOrder,
            educationStage: $row->educationStage,
            status: $row->status,
        );
    }
}
