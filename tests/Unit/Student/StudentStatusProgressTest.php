<?php

namespace Tests\Unit\Student;

use App\Application\Student\DTOs\StudentListPageDTO;
use App\Domain\Student\ValueObjects\StudentStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StudentStatusProgressTest extends TestCase
{
    #[Test]
    public function progress_uses_school_status_share_and_active_overall(): void
    {
        $progress = StudentListPageDTO::progressFromCounts([
            StudentStatus::Active->value => 2,
            StudentStatus::Suspended->value => 1,
            StudentStatus::Inactive->value => 1,
        ]);

        $this->assertSame(50, $progress['overall_percent']);
        $this->assertSame(100, $progress['stages'][0]['percent']);
        $this->assertSame(4, $progress['stages'][0]['count']);
        $this->assertNull($progress['stages'][0]['status']);
        $this->assertSame(StudentStatus::Active->value, $progress['stages'][1]['status']);
        $this->assertSame(50, $progress['stages'][1]['percent']);
        $this->assertSame(2, $progress['stages'][1]['count']);
        $this->assertSame(StudentStatus::Inactive->value, $progress['stages'][2]['status']);
        $this->assertSame(25, $progress['stages'][2]['percent']);
        $this->assertSame(1, $progress['stages'][2]['count']);
        $this->assertSame(StudentStatus::Suspended->value, $progress['stages'][3]['status']);
        $this->assertSame(25, $progress['stages'][3]['percent']);
        $this->assertSame(1, $progress['stages'][3]['count']);
        $this->assertSame(0, $progress['stages'][4]['percent']);
        $this->assertSame(0, $progress['stages'][4]['count']);
        $this->assertSame(0, $progress['stages'][5]['percent']);
        $this->assertSame(0, $progress['stages'][5]['count']);
    }

    #[Test]
    public function empty_counts_are_zero_percent(): void
    {
        $progress = StudentListPageDTO::progressFromCounts([]);

        $this->assertSame(0, $progress['overall_percent']);
        $this->assertSame(0, $progress['stages'][0]['percent']);
        $this->assertSame(0, $progress['stages'][0]['count']);
    }
}
