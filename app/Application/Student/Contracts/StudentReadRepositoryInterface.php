<?php

namespace App\Application\Student\Contracts;

use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;

interface StudentReadRepositoryInterface
{
    public function findDetail(int $studentId, int $schoolId): ?StudentDetailDTO;

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(?int $status, int $schoolId, int $page, int $perPage): array;

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function search(string $term, int $schoolId, int $page, int $perPage): array;
}
