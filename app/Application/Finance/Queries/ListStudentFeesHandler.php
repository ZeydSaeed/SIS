<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\StudentFeeDTO;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;

final class ListStudentFeesHandler
{
    public function __construct(
        private readonly StudentFeeRepositoryInterface $studentFees,
    ) {}

    /**
     * @return list<StudentFeeDTO>
     */
    public function handle(ListStudentFeesQuery $query): array
    {
        return array_map(
            static fn ($s): StudentFeeDTO => new StudentFeeDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                enrollmentId: $s->enrollmentId,
                feeTypeId: $s->feeTypeId,
                academicYearId: $s->academicYearId,
                amount: $s->amount,
                dueDate: $s->dueDate,
                status: $s->status,
                createdAt: $s->createdAt,
            ),
            $this->studentFees->listBySchool(
                $query->schoolId,
                $query->enrollmentId,
                $query->academicYearId,
                $query->feeStatus,
            ),
        );
    }
}
