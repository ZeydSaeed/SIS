<?php

namespace Tests\Unit\Academic;

use App\Domain\Academic\Services\AcademicYearDateWindow;
use PHPUnit\Framework\TestCase;

class AcademicYearDateWindowTest extends TestCase
{
    public function test_accepts_dates_inside_the_academic_year(): void
    {
        $this->assertTrue(AcademicYearDateWindow::contains(
            '2026-09-01',
            '2027-06-30',
            '2026-09-01T08:00',
            '2027-06-30T16:00',
        ));
    }

    public function test_rejects_start_before_academic_year(): void
    {
        $this->assertFalse(AcademicYearDateWindow::contains(
            '2026-09-01',
            '2027-06-30',
            '2026-08-31T08:00',
            '2026-09-30T16:00',
        ));
    }

    public function test_rejects_end_after_academic_year(): void
    {
        $this->assertFalse(AcademicYearDateWindow::contains(
            '2026-09-01',
            '2027-06-30',
            '2026-09-01T08:00',
            '2027-07-01T16:00',
        ));
    }
}
