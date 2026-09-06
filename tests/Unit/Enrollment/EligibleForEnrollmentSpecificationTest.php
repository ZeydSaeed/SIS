<?php

namespace Tests\Unit\Enrollment;

use App\Domain\Enrollment\Specifications\EligibleForEnrollmentSpecification;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\TestCase;

class EligibleForEnrollmentSpecificationTest extends TestCase
{
    public function test_active_student_is_eligible(): void
    {
        $spec = new EligibleForEnrollmentSpecification;
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Active);

        $this->assertTrue($spec->isSatisfiedBy($student));
        $this->assertSame([], $spec->unsatisfiedReasons($student));
    }

    public function test_suspended_student_is_not_eligible(): void
    {
        $spec = new EligibleForEnrollmentSpecification;
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Suspended);

        $this->assertFalse($spec->isSatisfiedBy($student));
        $this->assertNotEmpty($spec->unsatisfiedReasons($student));
    }
}
