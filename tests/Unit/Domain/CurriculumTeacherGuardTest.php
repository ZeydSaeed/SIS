<?php

namespace Tests\Unit\Domain;

use App\Domain\Teachers\Data\CurriculumLinePlacement;
use App\Domain\Teachers\Services\CurriculumTeacherGuard;
use App\Domain\Teachers\ValueObjects\TeachingRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CurriculumTeacherGuardTest extends TestCase
{
    private const MAIN = TeachingRole::Main;

    private const ASSISTANT = TeachingRole::Assistant;

    private const A = 41;

    private const B = 42;

    #[Test]
    public function several_main_teachers_per_section_and_assistants_need_one_covering_them(): void
    {
        $guard = new CurriculumTeacherGuard;
        $line = $this->line();
        $mainA = [$this->row(1, 100, self::MAIN, null, self::A)];

        $this->assertNull($guard->assignError($line, 100, self::MAIN, null, self::A, []));
        $this->assertSame('teachers.curriculum_assistant_needs_main', $guard->assignError($line, 200, self::ASSISTANT, null, self::A, []));
        $this->assertNull($guard->assignError($line, 101, self::MAIN, null, self::A, $mainA));
        $this->assertNull($guard->assignError($line, 200, self::ASSISTANT, null, self::A, $mainA));
        // A main teacher of section A does not cover B, nor «all sections».
        $this->assertSame('teachers.curriculum_assistant_needs_main', $guard->assignError($line, 200, self::ASSISTANT, null, self::B, $mainA));
        $this->assertSame('teachers.curriculum_assistant_needs_main', $guard->assignError($line, 200, self::ASSISTANT, null, null, $mainA));
        // A teacher of all sections covers every section; he is not added again where his sections overlap.
        $all = [$this->row(2, 300, self::MAIN, null, null)];
        $this->assertNull($guard->assignError($line, 200, self::ASSISTANT, null, self::B, $all));
        $this->assertSame('teachers.curriculum_teacher_exists', $guard->assignError($line, 300, self::MAIN, null, self::A, $all));
        $this->assertSame('teachers.teaching_role_invalid', $guard->assignError($line, 1, 3, null, null, []));
    }

    #[Test]
    public function parts_have_their_own_teachers_and_do_not_mix_with_the_whole_subject(): void
    {
        $guard = new CurriculumTeacherGuard;
        $line = $this->line(types: [3, 4]);
        $practical = [$this->row(1, 100, self::MAIN, 3, null)];

        $this->assertNull($guard->assignError($line, 101, self::MAIN, 4, null, $practical));
        $this->assertNull($guard->assignError($line, 102, self::ASSISTANT, 3, self::A, $practical));
        $this->assertSame('teachers.curriculum_assistant_needs_main', $guard->assignError($line, 102, self::ASSISTANT, 4, null, $practical));
        $this->assertSame('teachers.curriculum_part_conflict', $guard->assignError($line, 103, self::MAIN, null, null, $practical));
        $this->assertSame('teachers.teaching_part_invalid', $guard->assignError($line, 103, self::MAIN, 1, null, []));
        // The same teacher may take both parts.
        $this->assertNull($guard->assignError($line, 100, self::MAIN, 4, null, $practical));
    }

    #[Test]
    public function a_line_without_its_place_or_inactive_takes_no_teacher(): void
    {
        $guard = new CurriculumTeacherGuard;

        $this->assertSame('teachers.curriculum_not_placed', $guard->assignError($this->line(classId: null), 1, self::MAIN, null, null, []));
        $this->assertSame('teachers.curriculum_line_inactive', $guard->assignError($this->line(active: false), 1, self::MAIN, null, null, []));
        $this->assertSame('teachers.curriculum_section_mismatch', $guard->assignError($this->line(sectionId: self::A), 1, self::MAIN, null, self::B, []));
    }

    #[Test]
    public function a_main_teacher_leaves_only_when_his_assistants_stay_covered(): void
    {
        $guard = new CurriculumTeacherGuard;
        $first = $this->row(1, 100, self::MAIN, null, self::A);
        $second = $this->row(2, 101, self::MAIN, null, self::A);
        $assistant = $this->row(3, 200, self::ASSISTANT, null, self::A);

        $this->assertNull($guard->endError($first, [$first, $second, $assistant]));
        $this->assertSame('teachers.curriculum_main_has_assistants', $guard->endError($first, [$first, $assistant]));
        $this->assertNull($guard->endError($assistant, [$first, $assistant]));
    }

    /** @return array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int} */
    private function row(int $id, int $teacher, int $role, ?int $part, ?int $section): array
    {
        return ['id' => $id, 'teacher_id' => $teacher, 'role' => $role, 'subject_type' => $part, 'section_id' => $section];
    }

    /** @param  list<int>  $types */
    private function line(?int $classId = 8, ?int $sectionId = null, bool $active = true, array $types = [1]): CurriculumLinePlacement
    {
        return new CurriculumLinePlacement(1, 2, 3, 4, 5, 6, 7, $classId, $sectionId, $active, true, $types);
    }
}
