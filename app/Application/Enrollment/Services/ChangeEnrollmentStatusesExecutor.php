<?php

namespace App\Application\Enrollment\Services;

use App\Application\Contracts\OutboxRepository;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesCommand;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentReopened;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Services\BulkEnrollmentStatusGuard;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Applies bulk enrollment status transitions (extracted from handler for ARCH-103).
 */
final class ChangeEnrollmentStatusesExecutor
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly BulkEnrollmentStatusGuard $guard,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @param  list<int>  $enrollmentIds
     * @return array{updatedIds: list<int>, skippedIds: list<int>}
     */
    public function applyAll(ChangeEnrollmentStatusesCommand $command, array $enrollmentIds): array
    {
        $updatedIds = [];
        $skippedIds = [];

        foreach ($enrollmentIds as $enrollmentId) {
            if ($this->applyOne($command, $enrollmentId)) {
                $updatedIds[] = $enrollmentId;
            } else {
                $skippedIds[] = $enrollmentId;
            }
        }

        return [
            'updatedIds' => array_values(array_unique($updatedIds)),
            'skippedIds' => array_values(array_unique($skippedIds)),
        ];
    }

    private function applyOne(ChangeEnrollmentStatusesCommand $command, int $enrollmentId): bool
    {
        try {
            $enrollment = $this->guard->requireForSchool($enrollmentId, $command->schoolId);

            return match ($command->status) {
                EnrollmentStatus::ACTIVE => $this->activate($command, $enrollment),
                EnrollmentStatus::INACTIVE => $this->moveToClosed(
                    $command,
                    $enrollment,
                    EnrollmentStatus::INACTIVE,
                ),
                EnrollmentStatus::CANCELLED => $this->moveToClosed(
                    $command,
                    $enrollment,
                    EnrollmentStatus::CANCELLED,
                ),
                EnrollmentStatus::DISMISSED => $this->moveToClosed(
                    $command,
                    $enrollment,
                    EnrollmentStatus::DISMISSED,
                ),
                EnrollmentStatus::TRANSFERRED => $this->moveToClosed(
                    $command,
                    $enrollment,
                    EnrollmentStatus::TRANSFERRED,
                ),
                default => throw SisDomainException::withCode('enrollment.invalid_status'),
            };
        } catch (\Throwable) {
            // One invalid row must not abort single / multi / select-all batches
            // (inactive / cancelled / dismissed / transferred mixes included).
            return false;
        }
    }

    private function activate(ChangeEnrollmentStatusesCommand $command, EnrollmentSnapshot $enrollment): bool
    {
        if ($enrollment->isActive()) {
            return true;
        }

        if (! $this->guard->assertCanActivate($enrollment)) {
            return false;
        }

        if (! $this->enrollments->reopen($enrollment->id)) {
            return false;
        }

        $this->outbox->stage(new EnrollmentReopened(
            enrollmentId: $enrollment->id,
            studentId: $enrollment->studentId,
            schoolId: $command->schoolId,
            academicYearId: $enrollment->academicYearId,
            occurredAt: new \DateTimeImmutable,
        ));

        return true;
    }

    private function moveToClosed(
        ChangeEnrollmentStatusesCommand $command,
        EnrollmentSnapshot $enrollment,
        int $targetStatus,
    ): bool {
        if ($enrollment->status === $targetStatus) {
            // Already in the requested closed state — treat as success so
            // mixed select-all batches are not reported as total failure.
            return true;
        }

        $effectiveTo = $this->resolveEffectiveTo($command->effectiveTo, $enrollment->effectiveFrom);

        if ($enrollment->isActive()) {
            if ($targetStatus === EnrollmentStatus::INACTIVE) {
                $this->enrollments->deactivate($enrollment->id, $effectiveTo);

                return true;
            }

            if ($targetStatus === EnrollmentStatus::CANCELLED) {
                $this->enrollments->cancel($enrollment->id, $effectiveTo);
                $this->outbox->stage(new EnrollmentCancelled(
                    enrollmentId: $enrollment->id,
                    studentId: $enrollment->studentId,
                    schoolId: $enrollment->schoolId,
                    academicYearId: $enrollment->academicYearId,
                    effectiveTo: $effectiveTo,
                    cancelledBy: $command->actedBy,
                    occurredAt: new \DateTimeImmutable,
                ));

                return true;
            }

            // Active → transferred / dismissed (force path; closeAsTransferred is optimistic).
            $this->enrollments->setClosedStatus(
                $enrollment->id,
                $targetStatus,
                $effectiveTo,
            );

            return true;
        }

        // Closed → closed (inactive / cancelled / dismissed / transferred).
        $this->enrollments->setClosedStatus($enrollment->id, $targetStatus, $effectiveTo);

        if ($targetStatus === EnrollmentStatus::CANCELLED) {
            $this->outbox->stage(new EnrollmentCancelled(
                enrollmentId: $enrollment->id,
                studentId: $enrollment->studentId,
                schoolId: $enrollment->schoolId,
                academicYearId: $enrollment->academicYearId,
                effectiveTo: $effectiveTo,
                cancelledBy: $command->actedBy,
                occurredAt: new \DateTimeImmutable,
            ));
        }

        return true;
    }

    private function resolveEffectiveTo(string $requested, string $effectiveFrom): string
    {
        return $requested < $effectiveFrom ? $effectiveFrom : $requested;
    }
}
