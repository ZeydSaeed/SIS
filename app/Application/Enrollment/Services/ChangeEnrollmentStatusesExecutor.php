<?php

namespace App\Application\Enrollment\Services;

use App\Application\Contracts\OutboxRepository;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesCommand;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Events\EnrollmentReopened;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Services\BulkEnrollmentStatusGuard;
use App\Domain\Enrollment\Services\StudentEnrollmentStatusSyncPolicy;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentStatus;

/**
 * Applies bulk unified student-status changes from the enrollments roster
 * and syncs placement (enrollment) rows in the same unit of work.
 */
final class ChangeEnrollmentStatusesExecutor
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly StudentRepositoryInterface $students,
        private readonly BulkEnrollmentStatusGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly StudentEnrollmentStatusSyncPolicy $policy,
        private readonly ApplyStudentEnrollmentStatusSync $statusSync,
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
            $studentStatus = StudentStatus::tryFrom($command->status);
            if ($studentStatus === null) {
                throw SisDomainException::withCode('enrollment.invalid_status');
            }

            $enrollment = $this->guard->requireForSchool($enrollmentId, $command->schoolId);
            $targetEnrollmentStatus = $this->policy->enrollmentStatusFor($studentStatus);

            $student = $this->students->findByIdForSchool($enrollment->studentId, $command->schoolId);
            if ($student === null) {
                return false;
            }

            if ($student->status() !== $studentStatus) {
                $this->students->updateStatus($enrollment->studentId, $studentStatus->value);
            }

            $updated = match ($targetEnrollmentStatus) {
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
                default => throw SisDomainException::withCode('enrollment.invalid_status'),
            };

            if ($updated) {
                // Keep other operable rows for the same student aligned.
                $this->statusSync->syncEnrollmentsFromStudent(
                    schoolId: $command->schoolId,
                    studentIds: [$enrollment->studentId],
                    studentStatus: $studentStatus,
                    effectiveTo: $this->resolveEffectiveTo(
                        $command->effectiveTo,
                        $enrollment->effectiveFrom,
                    ),
                );
            }

            return $updated;
        } catch (\Throwable) {
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
        }

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
