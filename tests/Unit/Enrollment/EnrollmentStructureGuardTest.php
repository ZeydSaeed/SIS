<?php

namespace Tests\Unit\Enrollment;

use App\Domain\Enrollment\Services\EnrollmentStructureGuard;
use PHPUnit\Framework\TestCase;

class EnrollmentStructureGuardTest extends TestCase
{
    public function test_name_rules(): void
    {
        $guard = new EnrollmentStructureGuard;

        $this->assertNull($guard->nameError('الأول', false, 'enrollment.class'));
        $this->assertSame('enrollment.class_name_invalid', $guard->nameError('  ', false, 'enrollment.class'));
        $this->assertSame('enrollment.section_name_invalid', $guard->nameError(str_repeat('أ', 101), false, 'enrollment.section'));
        $this->assertSame('enrollment.class_name_taken', $guard->nameError('الأول', true, 'enrollment.class'));
    }

    public function test_capacity_rules(): void
    {
        $guard = new EnrollmentStructureGuard;

        $this->assertNull($guard->capacityError(null, 40, 'enrollment.section'));
        $this->assertNull($guard->capacityError(40, 40, 'enrollment.section'));
        $this->assertSame('enrollment.section_capacity_invalid', $guard->capacityError(0, 0, 'enrollment.section'));
        $this->assertSame('enrollment.class_capacity_invalid', $guard->capacityError(501, 0, 'enrollment.class'));
        $this->assertSame('enrollment.section_capacity_below_enrolled', $guard->capacityError(39, 40, 'enrollment.section'));
    }

    public function test_grade_level_changes_only_without_active_enrollments(): void
    {
        $guard = new EnrollmentStructureGuard;

        $this->assertNull($guard->gradeLevelChangeError(1, 1, 30));
        $this->assertNull($guard->gradeLevelChangeError(1, 2, 0));
        $this->assertSame('enrollment.class_grade_level_locked', $guard->gradeLevelChangeError(1, 2, 1));
    }
}
