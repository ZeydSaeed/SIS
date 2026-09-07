<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\DTOs\EnrollmentListPageDTO;

final class ListEnrollmentsHandler implements QueryHandler
{
    public function __construct(
        private readonly EnrollmentReadRepositoryInterface $enrollments,
    ) {}

    public function handle(Query $query): EnrollmentListPageDTO
    {
        assert($query instanceof ListEnrollmentsQuery);

        $page = $this->enrollments->paginate(
            $query->schoolId,
            $query->academicYearId,
            $query->page,
            $query->perPage,
        );

        return new EnrollmentListPageDTO(
            items: $page['items'],
            pagination: $page['pagination'],
        );
    }
}
