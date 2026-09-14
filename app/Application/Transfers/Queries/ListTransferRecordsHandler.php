<?php

namespace App\Application\Transfers\Queries;

use App\Application\Transfers\DTOs\TransferRecordDTO;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;

final class ListTransferRecordsHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
    ) {}

    /** @return list<TransferRecordDTO> */
    public function handle(ListTransferRecordsQuery $query): array
    {
        return array_map(
            static fn ($row): TransferRecordDTO => new TransferRecordDTO(
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
            ),
            $this->transfers->listRecordsForSchool($query->schoolId),
        );
    }
}
