<?php

namespace App\Application\Enrollment\Services;

use App\Application\Contracts\OutboxRepository;
use App\Domain\Enrollment\Events\ClassDeactivated;
use App\Domain\Enrollment\Events\ClassReactivated;
use App\Domain\Enrollment\Events\SectionDeactivated;
use App\Domain\Enrollment\Events\SectionReactivated;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStructureStatus;

/**
 * «الحالة» set from the class / section form (نشط · غير نشط): moves the row to the requested status with the same
 * repository transition and outbox event as the deactivate / reactivate commands. Call inside the save transaction.
 */
final class ApplyStructureStatusChange
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
        private readonly OutboxRepository $outbox,
    ) {}

    public function forClass(int $schoolId, int $classId, int $current, ?int $requested, string $at): void
    {
        if ($requested === null || $requested === $current) {
            return;
        }
        if ($requested === EnrollmentStructureStatus::Inactive->value) {
            $this->structure->deactivateClass($schoolId, $classId, $at);
            $this->outbox->stage(new ClassDeactivated($classId, $schoolId, new \DateTimeImmutable));

            return;
        }
        $this->structure->reactivateClass($schoolId, $classId, $at);
        $this->outbox->stage(new ClassReactivated($classId, $schoolId, new \DateTimeImmutable));
    }

    public function forSection(int $schoolId, int $sectionId, int $classId, int $current, ?int $requested, string $at): void
    {
        if ($requested === null || $requested === $current) {
            return;
        }
        if ($requested === EnrollmentStructureStatus::Inactive->value) {
            $this->structure->deactivateSection($schoolId, $sectionId, $at);
            $this->outbox->stage(new SectionDeactivated($sectionId, $classId, $schoolId, new \DateTimeImmutable));

            return;
        }
        $this->structure->reactivateSection($schoolId, $sectionId, $at);
        $this->outbox->stage(new SectionReactivated($sectionId, $classId, $schoolId, new \DateTimeImmutable));
    }
}
