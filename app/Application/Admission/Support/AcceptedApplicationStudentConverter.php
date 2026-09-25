<?php

namespace App\Application\Admission\Support;

use App\Application\Admission\Commands\ConvertApplicationToStudentCommand;
use App\Application\Admission\Commands\ConvertApplicationToStudentHandler;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;

/**
 * Converts an Accepted application to a student record.
 * Non-final so unit tests can stub without mocking the final command handler.
 */
class AcceptedApplicationStudentConverter
{
    public function __construct(
        private readonly ConvertApplicationToStudentHandler $convertToStudent,
        private readonly AdmissionRepositoryInterface $admission,
    ) {}

    public function convert(
        int $schoolId,
        int $applicationId,
        ?int $reviewedBy = null,
        ?string $idempotencyKey = null,
    ): void {
        $this->convertToStudent->handle(new ConvertApplicationToStudentCommand(
            schoolId: $schoolId,
            applicationId: $applicationId,
            reviewedBy: $reviewedBy,
            idempotencyKey: $idempotencyKey,
        ));
    }

    /**
     * Convert Accepted applications that never received a student row.
     * Failures are skipped so a single bad row does not block the rest.
     */
    public function repairOrphans(int $schoolId, ?int $reviewedBy = null): void
    {
        foreach ($this->admission->findAcceptedApplicationIdsWithoutStudent($schoolId) as $applicationId) {
            try {
                $this->convert(
                    schoolId: $schoolId,
                    applicationId: $applicationId,
                    reviewedBy: $reviewedBy,
                    idempotencyKey: 'repair-accepted:'.$schoolId.':'.$applicationId,
                );
            } catch (\Throwable) {
                // Leave the row Accepted; an operator can fix data and retry Accept/convert.
            }
        }
    }
}
