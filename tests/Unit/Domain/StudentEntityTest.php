<?php

namespace Tests\Unit\Domain;

use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Student\Entities\Student;
use App\Domain\Student\ValueObjects\StudentCode;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\TestCase;

class StudentEntityTest extends TestCase
{
    public function test_active_student_can_enroll(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Active);

        $this->assertTrue($student->canEnroll());
    }

    public function test_suspend_changes_status(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Active);
        $student->suspend();

        $this->assertSame(StudentStatus::Suspended, $student->status());
        $this->assertFalse($student->canEnroll());
    }

    public function test_cannot_activate_graduated_student(): void
    {
        $student = Student::reconstitute(1, new StudentCode('STU-001'), 'Ali Hassan', StudentStatus::Graduated);

        $this->expectException(SisDomainException::class);
        $student->activate();
    }
}
