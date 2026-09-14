<?php

namespace App\Application\Transfers\Queries;

use App\Application\Transfers\DTOs\TransferRequestDTO;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;

final class GetTransferRequestHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
    ) {}

    public function handle(GetTransferRequestQuery $query): ?TransferRequestDTO
    {
        $row = $this->transfers->findRequestForSchool($query->transferRequestId, $query->schoolId);
        if ($row === null) {
            return null;
        }

        return new TransferRequestDTO(
            id: $row->id,
            studentId: $row->studentId,
            fromSchoolId: $row->fromSchoolId,
            toSchoolId: $row->toSchoolId,
            fromEnrollmentId: $row->fromEnrollmentId,
            academicYearId: $row->academicYearId,
            reason: $row->reason,
            status: $row->status,
            requestedBy: $row->requestedBy,
            requestedAt: $row->requestedAt,
            approvedBy: $row->approvedBy,
            approvedAt: $row->approvedAt,
            createdAt: $row->createdAt,
        );
    }
}
