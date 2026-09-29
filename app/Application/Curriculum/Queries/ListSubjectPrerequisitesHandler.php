<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\PrerequisiteDTO;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;

final class ListSubjectPrerequisitesHandler
{
    public function __construct(
        private readonly PrerequisiteRepositoryInterface $prerequisites,
    ) {}

    /**
     * @return list<PrerequisiteDTO>
     */
    public function handle(ListSubjectPrerequisitesQuery $query): array
    {
        $rows = $query->includeInactive
            ? $this->prerequisites->listForSubject($query->subjectId)
            : $this->prerequisites->listActiveForSubject($query->subjectId);

        return array_map(
            fn ($row): PrerequisiteDTO => new PrerequisiteDTO(
                id: $row->id,
                subjectId: $row->subjectId,
                prerequisiteSubjectId: $row->prerequisiteSubjectId,
                status: $row->status,
                createdAt: $row->createdAt,
            ),
            $rows,
        );
    }
}
