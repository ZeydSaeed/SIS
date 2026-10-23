<?php

namespace App\Application\Exams\Queries;

use App\Domain\Exams\Data\ExamTypeSnapshot;
use App\Domain\Exams\Repositories\ExamTypeRepositoryInterface;

final class ListExamTypesHandler
{
    public function __construct(
        private readonly ExamTypeRepositoryInterface $types,
    ) {}

    /**
     * @return list<array{id: int, code: string, name: string, weight_percentage: int, in_use: bool}>
     */
    public function handle(ListExamTypesQuery $query): array
    {
        return array_map(static fn (ExamTypeSnapshot $type): array => [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'weight_percentage' => $type->weightPercentage,
            'in_use' => $type->inUse,
        ], $this->types->listForSchool($query->schoolId));
    }
}
