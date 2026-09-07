<?php

namespace App\Application\Student\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Student\Contracts\StudentReadRepositoryInterface;
use App\Application\Student\DTOs\StudentDetailDTO;
use App\Domain\Student\Exceptions\StudentNotFoundException;

final class GetStudentHandler implements QueryHandler
{
    public function __construct(
        private readonly StudentReadRepositoryInterface $students,
    ) {}

    public function handle(Query $query): StudentDetailDTO
    {
        assert($query instanceof GetStudentQuery);

        $detail = $this->students->findDetail($query->studentId, $query->schoolId);
        if ($detail === null) {
            throw StudentNotFoundException::forId($query->studentId);
        }

        return $detail;
    }
}
