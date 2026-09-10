<?php

namespace Tests\Unit\Domain\Admission;

use App\Domain\Admission\ValueObjects\ApplicationStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationStatusTest extends TestCase
{
    #[Test]
    public function converted_and_rejected_are_terminal(): void
    {
        $this->assertTrue(ApplicationStatus::Converted->isTerminal());
        $this->assertTrue(ApplicationStatus::Rejected->isTerminal());
        $this->assertFalse(ApplicationStatus::Submitted->isTerminal());
    }

    #[Test]
    public function only_accepted_can_convert_to_student(): void
    {
        $this->assertTrue(ApplicationStatus::Accepted->canConvertToStudent());
        $this->assertFalse(ApplicationStatus::Waitlisted->canConvertToStudent());
        $this->assertFalse(ApplicationStatus::Converted->canConvertToStudent());
    }
}
