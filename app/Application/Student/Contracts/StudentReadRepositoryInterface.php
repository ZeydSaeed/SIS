<?php

namespace App\Application\Student\Contracts;

use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;

interface StudentReadRepositoryInterface
{
    public function findDetail(int $studentId): ?StudentDetailDTO;

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(?int $status, int $page, int $perPage): array;

    /**
     * @return array{items: list<StudentListItemDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function search(string $term, int $page, int $perPage): array;
}
