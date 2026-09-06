<?php

namespace Tests\Unit\Enrollment;

use App\Domain\Enrollment\Data\StudentEnrollmentView;
use App\Domain\Enrollment\Specifications\EligibleForEnrollmentSpecification;
use PHPUnit\Framework\TestCase;

class EligibleForEnrollmentSpecificationTest extends TestCase
{
    public function test_active_student_is_eligible(): void
    {
        $spec = new EligibleForEnrollmentSpecification;
        $student = new StudentEnrollmentView(1, 1, 'STU-001', 'Ali Hassan');

        $this->assertTrue($spec->isSatisfiedBy($student));
        $this->assertSame([], $spec->unsatisfiedReasons($student));
    }

    public function test_inactive_student_is_not_eligible(): void
    {
        $spec = new EligibleForEnrollmentSpecification;
        $student = new StudentEnrollmentView(1, 0, 'STU-001', 'Ali Hassan');

        $this->assertFalse($spec->isSatisfiedBy($student));
        $this->assertNotEmpty($spec->unsatisfiedReasons($student));
    }
}
