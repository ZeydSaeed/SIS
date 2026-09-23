<?php

namespace Tests\Unit\Enrollment;

use App\Domain\Enrollment\Services\StudentEnrollmentStatusSyncPolicy;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StudentEnrollmentStatusSyncPolicyTest extends TestCase
{
    #[Test]
    public function maps_student_statuses_to_enrollment_placement_statuses(): void
    {
        $policy = new StudentEnrollmentStatusSyncPolicy;

        $this->assertSame(EnrollmentStatus::ACTIVE, $policy->enrollmentStatusFor(StudentStatus::Active));
        $this->assertSame(EnrollmentStatus::INACTIVE, $policy->enrollmentStatusFor(StudentStatus::Inactive));
        $this->assertSame(EnrollmentStatus::INACTIVE, $policy->enrollmentStatusFor(StudentStatus::Suspended));
        $this->assertSame(EnrollmentStatus::INACTIVE, $policy->enrollmentStatusFor(StudentStatus::Graduated));
        $this->assertSame(EnrollmentStatus::CANCELLED, $policy->enrollmentStatusFor(StudentStatus::Withdrawn));
    }

    #[Test]
    public function maps_enrollment_statuses_to_student_statuses(): void
    {
        $policy = new StudentEnrollmentStatusSyncPolicy;

        $this->assertSame(StudentStatus::Active, $policy->studentStatusFor(EnrollmentStatus::ACTIVE));
        $this->assertSame(StudentStatus::Inactive, $policy->studentStatusFor(EnrollmentStatus::INACTIVE));
        $this->assertSame(StudentStatus::Withdrawn, $policy->studentStatusFor(EnrollmentStatus::CANCELLED));
        $this->assertSame(StudentStatus::Withdrawn, $policy->studentStatusFor(EnrollmentStatus::DISMISSED));
        $this->assertSame(StudentStatus::Inactive, $policy->studentStatusFor(EnrollmentStatus::TRANSFERRED));
        $this->assertNull($policy->studentStatusFor(EnrollmentStatus::SUPERSEDED));
    }
}
