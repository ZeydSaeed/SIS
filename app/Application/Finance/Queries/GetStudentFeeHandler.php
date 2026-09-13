<?php

namespace App\Application\Finance\Queries;

use App\Application\Finance\DTOs\StudentFeeDTO;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;

final class GetStudentFeeHandler
{
    public function __construct(
        private readonly StudentFeeRepositoryInterface $studentFees,
    ) {}

    public function handle(GetStudentFeeQuery $query): ?StudentFeeDTO
    {
        $s = $this->studentFees->findById($query->schoolId, $query->studentFeeId);
        if ($s === null) {
            return null;
        }

        return new StudentFeeDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            enrollmentId: $s->enrollmentId,
            feeTypeId: $s->feeTypeId,
            academicYearId: $s->academicYearId,
            amount: $s->amount,
            dueDate: $s->dueDate,
            status: $s->status,
            createdAt: $s->createdAt,
        );
    }
}
