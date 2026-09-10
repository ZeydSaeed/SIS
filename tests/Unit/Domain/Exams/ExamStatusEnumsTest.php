<?php

namespace Tests\Unit\Domain\Exams;

use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamStatusEnumsTest extends TestCase
{
    #[Test]
    public function exam_status_terminals(): void
    {
        $this->assertTrue(ExamStatus::Completed->isTerminal());
        $this->assertTrue(ExamStatus::Cancelled->isTerminal());
        $this->assertFalse(ExamStatus::Scheduled->isTerminal());
    }

    #[Test]
    public function session_status_terminals(): void
    {
        $this->assertTrue(ExamSessionStatus::Completed->isTerminal());
        $this->assertFalse(ExamSessionStatus::Scheduled->isTerminal());
    }

    #[Test]
    public function enrollment_active_seat_semantics(): void
    {
        $this->assertTrue(ExamEnrollmentStatus::Registered->isActiveSeat());
        $this->assertTrue(ExamEnrollmentStatus::Present->isActiveSeat());
        $this->assertFalse(ExamEnrollmentStatus::Withdrawn->isActiveSeat());
        $this->assertFalse(ExamEnrollmentStatus::Absent->isActiveSeat());
    }
}
