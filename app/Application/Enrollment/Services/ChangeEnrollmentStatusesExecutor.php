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
     */
    public function applyAll(ChangeEnrollmentStatusesCommand $command, array $enrollmentIds): void
    {
        foreach ($enrollmentIds as $enrollmentId) {
            $this->applyOne($command, $enrollmentId);
        }
    }

    private function applyOne(ChangeEnrollmentStatusesCommand $command, int $enrollmentId): void
    {
        $enrollment = $this->guard->requireForSchool($enrollmentId, $command->schoolId);

        match ($command->status) {
            EnrollmentStatus::ACTIVE => $this->activate($command, $enrollment),
            EnrollmentStatus::INACTIVE => $this->deactivate($command, $enrollment),
            EnrollmentStatus::CANCELLED => $this->cancel($command, $enrollment),
            EnrollmentStatus::TRANSFERRED => $this->transfer($command, $enrollment->id),
            default => throw SisDomainException::withCode('enrollment.invalid_status'),
        };
    }

    private function activate(ChangeEnrollmentStatusesCommand $command, EnrollmentSnapshot $enrollment): void
    {
        if (! $this->guard->assertCanActivate($enrollment)) {
            return;
        }

        if (! $this->enrollments->reopen($enrollment->id)) {
            throw SisDomainException::withCode('enrollment.reopen_failed');
        }

        $this->outbox->stage(new EnrollmentReopened(
            enrollmentId: $enrollment->id,
            studentId: $enrollment->studentId,
            schoolId: $command->schoolId,
            academicYearId: $enrollment->academicYearId,
            occurredAt: new \DateTimeImmutable,
        ));
    }

    private function deactivate(ChangeEnrollmentStatusesCommand $command, EnrollmentSnapshot $enrollment): void
    {
        $this->guard->assertCanCloseActive($enrollment, $command->effectiveTo);
        $this->enrollments->deactivate($enrollment->id, $command->effectiveTo);
    }

    private function cancel(ChangeEnrollmentStatusesCommand $command, EnrollmentSnapshot $enrollment): void
    {
        $this->guard->assertCanCloseActive($enrollment, $command->effectiveTo);
        $this->enrollments->cancel($enrollment->id, $command->effectiveTo);
        $this->outbox->stage(new EnrollmentCancelled(
            enrollmentId: $enrollment->id,
            studentId: $enrollment->studentId,
            schoolId: $enrollment->schoolId,
            academicYearId: $enrollment->academicYearId,
            effectiveTo: $command->effectiveTo,
            cancelledBy: $command->actedBy,
            occurredAt: new \DateTimeImmutable,
        ));
    }

    private function transfer(ChangeEnrollmentStatusesCommand $command, int $enrollmentId): void
    {
        if (! $this->enrollments->closeAsTransferred($enrollmentId, $command->schoolId, $command->effectiveTo)) {
            throw SisDomainException::withCode('enrollment.transfer_failed');
        }
    }
}
