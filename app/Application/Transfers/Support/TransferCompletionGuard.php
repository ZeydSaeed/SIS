<?php

namespace App\Application\Transfers\Support;

use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Transfers\Data\TransferRequestSnapshot;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;

final class TransferCompletionGuard
{
    public function __construct(
        private readonly TransferRepositoryInterface $transfers,
        private readonly EnrollmentPlacementRepositoryInterface $placement,
    ) {}

    /**
     * @return array{0: ?TransferRequestSnapshot, 1: ?string} [request, errorCode]
     */
    public function resolveApprovedRequest(int $transferRequestId, int $toSchoolId): array
    {
        $req = $this->transfers->findRequestForSchool($transferRequestId, $toSchoolId);
        if ($req === null || $req->toSchoolId !== $toSchoolId) {
            return [null, 'transfers.request_not_found'];
        }
        if ($req->status === TransferRequestStatus::Completed) {
            return [null, 'transfers.already_completed'];
        }
        if ($req->status !== TransferRequestStatus::Approved) {
            return [null, 'transfers.not_approved'];
        }

        return [$req, null];
    }

    public function destinationPlacementError(
        int $toClassId,
        int $toSectionId,
        int $toSchoolId,
        int $academicYearId,
    ): ?string {
        if (! $this->placement->classBelongsToSchool($toClassId, $toSchoolId, $academicYearId)) {
            return 'transfers.destination_class_invalid';
        }
        if (! $this->placement->sectionBelongsToClass($toSectionId, $toClassId)) {
            return 'transfers.destination_section_invalid';
        }

        return null;
    }
}
