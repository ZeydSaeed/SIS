<?php

namespace App\Application\Transfers\Queries;

use App\Application\Transfers\DTOs\TransferRecordDTO;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;

final class GetTransferRecordHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
    ) {}

    public function handle(GetTransferRecordQuery $query): ?TransferRecordDTO
    {
        $row = $this->transfers->findRecordForSchool($query->schoolId, $query->recordId);

        if ($row === null) {
            return null;
        }

        return new TransferRecordDTO(
            id: $row->id,
            transferRequestId: $row->transferRequestId,
            studentId: $row->studentId,
            fromSchoolId: $row->fromSchoolId,
            toSchoolId: $row->toSchoolId,
            fromEnrollmentId: $row->fromEnrollmentId,
            toEnrollmentId: $row->toEnrollmentId,
            effectiveDate: $row->effectiveDate,
            completedAt: $row->completedAt,
            createdAt: $row->createdAt,
        );
    }
}
