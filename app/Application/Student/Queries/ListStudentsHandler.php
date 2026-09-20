<?php

namespace App\Application\Student\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentListPageDTO;

final class ListStudentsHandler implements QueryHandler
{
    public function __construct(
        private readonly StudentReadRepositoryInterface $students,
    ) {}

    public function handle(Query $query): StudentListPageDTO
    {
        assert($query instanceof ListStudentsQuery);

        $page = $this->students->paginate(
            $query->status,
            $query->schoolId,
            $query->page,
            $query->perPage,
            $query->academicYearId,
            $query->gender,
        );

        return StudentListPageDTO::fromPage(
            $page,
            $this->students->countByStatus($query->schoolId, $query->academicYearId, $query->gender),
        );
    }
}
