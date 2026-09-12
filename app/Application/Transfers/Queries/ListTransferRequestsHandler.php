<?php

namespace App\Application\Transfers\Queries;

use App\Application\Transfers\DTOs\TransferRequestDTO;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;

final class ListTransferRequestsHandler
{
    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
    ) {}

    /**
     * @return list<TransferRequestDTO>
     */
    public function handle(ListTransferRequestsQuery $query): array
    {
        $items = [];
        foreach ($this->transfers->listRequestsForSchool(
            $query->schoolId,
            $query->academicYearId,
            $query->status,
        ) as $row) {
            $items[] = new TransferRequestDTO(
                $row->id,
                $row->studentId,
                $row->fromSchoolId,
                $row->toSchoolId,
                $row->fromEnrollmentId,
                $row->academicYearId,
                $row->reason,
                $row->status,
                $row->requestedBy,
                $row->requestedAt,
                $row->approvedBy,
                $row->approvedAt,
                $row->createdAt,
            );
        }

        return $items;
    }
}
