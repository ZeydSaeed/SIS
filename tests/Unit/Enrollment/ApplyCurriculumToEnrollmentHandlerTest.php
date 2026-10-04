<?php

namespace Tests\Unit\Enrollment;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentCommand;
use App\Application\Enrollment\Commands\ApplyCurriculumToEnrollmentHandler;
use App\Domain\Enrollment\Contracts\EnrollmentCurriculumPort;
use App\Domain\Enrollment\Contracts\PrerequisiteCatalogPort;
use App\Domain\Enrollment\Contracts\PrerequisitePassEvidencePort;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Events\EnrollmentSubjectAssigned;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\Services\AssignEnrollmentSubjectGuard;
use PHPUnit\Framework\TestCase;

class ApplyCurriculumToEnrollmentHandlerTest extends TestCase
{
    public function test_assigns_only_unlinked_required_subjects_and_skips_unmet_prerequisites(): void
    {
        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findByIdAndSchool')->willReturn(new EnrollmentSnapshot(
            id: 7,
            studentId: 3,
            schoolId: 10,
            academicYearId: 2026,
            classId: 5,
            sectionId: 12,
            specializationId: null,
            enrollmentNumber: 'ENR-7',
            status: 1,
            effectiveFrom: '2026-09-01',
            effectiveTo: null,
            departmentId: 4,
        ));

        // Required: 100, 200, 300. 100 is already linked (left untouched); 300 needs unpassed prerequisite 900.
        $curriculum = $this->createMock(EnrollmentCurriculumPort::class);
        $curriculum->method('requiredSubjectIdsForPlacement')->with(10, 2026, 5, 4)->willReturn([100, 200, 300]);
        $curriculum->method('subjectInGoverningCurriculum')->willReturn(true);

        $subjects = $this->createMock(EnrollmentSubjectRepositoryInterface::class);
        $subjects->method('linkedSubjectIds')->willReturn([100]);
        $subjects->expects($this->once())
            ->method('assignOrReactivate')
            ->with(10, 7, 200, false, $this->anything())
            ->willReturn(55);

        $catalog = $this->createMock(PrerequisiteCatalogPort::class);
        $catalog->method('subjectIsActive')->willReturn(true);
        $catalog->method('activePrerequisiteSubjectIds')->willReturnCallback(
            static fn (int $subjectId) => $subjectId === 300 ? [900] : [],
        );
        $passEvidence = $this->createMock(PrerequisitePassEvidencePort::class);
        $passEvidence->method('studentHasPassingGrade')->willReturn(false);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('transaction')->willReturnCallback(fn (callable $callback) => $callback());

        $outbox = $this->createMock(OutboxRepository::class);
        $outbox->expects($this->once())->method('stage')->with($this->isInstanceOf(EnrollmentSubjectAssigned::class));

        $handler = new ApplyCurriculumToEnrollmentHandler(
            $unitOfWork,
            $enrollments,
            $subjects,
            $curriculum,
            new AssignEnrollmentSubjectGuard($enrollments, $catalog, $passEvidence, $curriculum),
            $outbox,
            $this->createMock(IdempotencyStore::class),
        );

        $result = $handler->handle(new ApplyCurriculumToEnrollmentCommand(10, 7));

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->assignedCount);
        $this->assertSame(['enrollment.prerequisite_not_met:300'], $result->warnings);
    }

    public function test_missing_enrollment_fails_without_assigning(): void
    {
        $enrollments = $this->createMock(EnrollmentRepositoryInterface::class);
        $enrollments->method('findByIdAndSchool')->willReturn(null);
        $subjects = $this->createMock(EnrollmentSubjectRepositoryInterface::class);
        $subjects->expects($this->never())->method('assignOrReactivate');
        $curriculum = $this->createMock(EnrollmentCurriculumPort::class);

        $handler = new ApplyCurriculumToEnrollmentHandler(
            $this->createMock(UnitOfWork::class),
            $enrollments,
            $subjects,
            $curriculum,
            new AssignEnrollmentSubjectGuard(
                $enrollments,
                $this->createMock(PrerequisiteCatalogPort::class),
                $this->createMock(PrerequisitePassEvidencePort::class),
                $curriculum,
            ),
            $this->createMock(OutboxRepository::class),
            $this->createMock(IdempotencyStore::class),
        );

        $result = $handler->handle(new ApplyCurriculumToEnrollmentCommand(10, 99));

        $this->assertFalse($result->success);
        $this->assertSame(['enrollment.not_found'], $result->errors);
    }
}
