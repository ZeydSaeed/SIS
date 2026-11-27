<?php

namespace Tests\Unit\Domain;

use App\Domain\Teachers\Data\CurriculumLinePlacement;
use App\Domain\Teachers\Services\CurriculumTeacherGuard;
use App\Domain\Teachers\ValueObjects\TeachingRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CurriculumTeacherGuardTest extends TestCase
{
    #[Test]
    public function main_first_then_assistants_and_the_main_teacher_is_replaced_not_doubled(): void
    {
        $guard = new CurriculumTeacherGuard;
        $line = $this->line();

        $this->assertNull($guard->assignError($line, TeachingRole::Main, false, false, false));
        $this->assertSame('teachers.curriculum_assistant_needs_main', $guard->assignError($line, TeachingRole::Assistant, false, false, false));
        $this->assertNull($guard->assignError($line, TeachingRole::Assistant, true, false, false));
        $this->assertSame('teachers.curriculum_main_exists', $guard->assignError($line, TeachingRole::Main, true, false, false));
        $this->assertNull($guard->assignError($line, TeachingRole::Main, true, false, true));
        $this->assertSame('teachers.curriculum_teacher_exists', $guard->assignError($line, TeachingRole::Assistant, true, true, false));
        $this->assertSame('teachers.teaching_role_invalid', $guard->assignError($line, 3, true, false, false));
    }

    #[Test]
    public function a_line_without_its_place_or_inactive_takes_no_teacher(): void
    {
        $guard = new CurriculumTeacherGuard;

        $this->assertSame('teachers.curriculum_not_placed', $guard->assignError($this->line(sectionId: null), TeachingRole::Main, false, false, false));
        $this->assertSame('teachers.curriculum_line_inactive', $guard->assignError($this->line(active: false), TeachingRole::Main, false, false, false));
    }

    #[Test]
    public function the_main_teacher_stays_while_assistants_remain(): void
    {
        $guard = new CurriculumTeacherGuard;

        $this->assertSame('teachers.curriculum_main_has_assistants', $guard->endError(TeachingRole::Main, 2));
        $this->assertNull($guard->endError(TeachingRole::Main, 0));
        $this->assertNull($guard->endError(TeachingRole::Assistant, 2));
    }

    private function line(?int $sectionId = 7, bool $active = true): CurriculumLinePlacement
    {
        return new CurriculumLinePlacement(1, 2, 3, 4, 5, 6, 8, 9, $sectionId, $active, true);
    }
}
