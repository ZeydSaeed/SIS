<?php

namespace Tests\Unit\Attendance;

use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Specifications\EnrollmentValidForAttendanceSpecification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnrollmentValidForAttendanceSpecificationTest extends TestCase
{
    private function baseEnrollment(): EnrollmentAttendanceContext
    {
        return new EnrollmentAttendanceContext(
            id: 1,
            studentId: 10,
            schoolId: 2,
            academicYearId: 2026,
            sectionId: 5,
            effectiveFrom: '2026-09-01',
            effectiveTo: null,
            status: 1,
        );
    }

    #[Test]
    public function accepts_matching_open_ended_enrollment(): void
    {
        $spec = new EnrollmentValidForAttendanceSpecification(10, 2, 2026, 5, '2026-10-01');
        $this->assertTrue($spec->isSatisfiedBy($this->baseEnrollment()));
    }

    #[Test]
    public function rejects_future_effective_from(): void
    {
        $spec = new EnrollmentValidForAttendanceSpecification(10, 2, 2026, 5, '2026-08-15');
        $this->assertFalse($spec->isSatisfiedBy($this->baseEnrollment()));
    }

    #[Test]
    public function rejects_after_effective_to(): void
    {
        $enrollment = new EnrollmentAttendanceContext(
            1, 10, 2, 2026, 5, '2026-09-01', '2026-09-30', 2,
        );
        $spec = new EnrollmentValidForAttendanceSpecification(10, 2, 2026, 5, '2026-10-01');
        $this->assertFalse($spec->isSatisfiedBy($enrollment));
    }

    #[Test]
    public function accepts_cancelled_still_in_window(): void
    {
        $enrollment = new EnrollmentAttendanceContext(
            1, 10, 2, 2026, 5, '2026-09-01', '2026-10-15', 2,
        );
        $spec = new EnrollmentValidForAttendanceSpecification(10, 2, 2026, 5, '2026-10-01');
        $this->assertTrue($spec->isSatisfiedBy($enrollment));
    }

    #[Test]
    public function rejects_section_mismatch(): void
    {
        $spec = new EnrollmentValidForAttendanceSpecification(10, 2, 2026, 99, '2026-10-01');
        $this->assertFalse($spec->isSatisfiedBy($this->baseEnrollment()));
    }
}
