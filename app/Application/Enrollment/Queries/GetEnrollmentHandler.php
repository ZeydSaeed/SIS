<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\DTOs\EnrollmentDTO;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;

final class GetEnrollmentHandler implements QueryHandler
{
    public function __construct(
        private readonly EnrollmentReadRepositoryInterface $enrollments,
    ) {}

    public function handle(Query $query): EnrollmentDTO
    {
        assert($query instanceof GetEnrollmentQuery);

        $detail = $this->enrollments->findDetail($query->enrollmentId, $query->schoolId);
        if ($detail === null) {
            throw EnrollmentNotFoundException::forId($query->enrollmentId);
        }

        return $detail;
    }
}
