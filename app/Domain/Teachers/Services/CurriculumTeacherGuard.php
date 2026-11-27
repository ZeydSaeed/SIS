<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Data\CurriculumLinePlacement;
use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * Teachers of a curriculum subject line. Each assignment is for the whole subject or one part of it (one of its
 * types: «نظري» to one teacher, «عملي» to another), and for one section or all sections of the class (section null).
 * A part may have several main teachers and several assistants; an assistant needs a main teacher covering its part
 * and section. A whole-subject main teacher and part main teachers are not mixed on one line. A teacher is not added
 * twice where his sections overlap, and a main teacher cannot leave while an assistant would be left uncovered.
 *
 * Assignments are `array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int}`.
 */
final class CurriculumTeacherGuard
{
    /** @param  list<array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int}>  $assignments  active on the line */
    public function assignError(
        CurriculumLinePlacement $line,
        int $teacherId,
        int $role,
        ?int $part,
        ?int $section,
        array $assignments,
    ): ?string {
        if (! $line->lineActive || ! $line->curriculumActive) {
            return 'teachers.curriculum_line_inactive';
        }
        if (! $line->isPlaced()) {
            return 'teachers.curriculum_not_placed';
        }
        if (! TeachingRole::isValid($role)) {
            return 'teachers.teaching_role_invalid';
        }
        if ($part !== null && ! in_array($part, $line->subjectTypes, true)) {
            return 'teachers.teaching_part_invalid';
        }
        if ($line->sectionId !== null && $section !== null && $section !== $line->sectionId) {
            return 'teachers.curriculum_section_mismatch';
        }
        foreach ($assignments as $row) {
            if ($row['teacher_id'] === $teacherId && $row['subject_type'] === $part && self::overlaps($row['section_id'], $section)) {
                return 'teachers.curriculum_teacher_exists';
            }
        }
        $mains = array_values(array_filter($assignments, static fn (array $row): bool => $row['role'] === TeachingRole::Main));
        if ($role === TeachingRole::Main) {
            foreach ($mains as $main) {
                if (($part === null) !== ($main['subject_type'] === null)) {
                    return 'teachers.curriculum_part_conflict';
                }
            }

            return null;
        }

        return self::covered($part, $section, $mains) ? null : 'teachers.curriculum_assistant_needs_main';
    }

    /**
     * @param  array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int}  $leaving
     * @param  list<array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int}>  $assignments  active on the line
     */
    public function endError(array $leaving, array $assignments): ?string
    {
        if ($leaving['role'] !== TeachingRole::Main) {
            return null;
        }
        $mains = array_values(array_filter(
            $assignments,
            static fn (array $row): bool => $row['role'] === TeachingRole::Main && $row['id'] !== $leaving['id'],
        ));
        foreach ($assignments as $row) {
            if ($row['role'] === TeachingRole::Assistant && ! self::covered($row['subject_type'], $row['section_id'], $mains)) {
                return 'teachers.curriculum_main_has_assistants';
            }
        }

        return null;
    }

    /** Two section choices overlap when either is «all sections» (null) or they are the same section. */
    public static function overlaps(?int $a, ?int $b): bool
    {
        return $a === null || $b === null || $a === $b;
    }

    /**
     * A main teacher covers the part (his own part, or the whole subject) and the section (his own, or all of them).
     *
     * @param  list<array{id: int, teacher_id: int, role: int, subject_type: ?int, section_id: ?int}>  $mains
     */
    private static function covered(?int $part, ?int $section, array $mains): bool
    {
        foreach ($mains as $main) {
            $partOk = $main['subject_type'] === null || $main['subject_type'] === $part;
            $sectionOk = $main['section_id'] === null || $main['section_id'] === $section;
            if ($partOk && $sectionOk) {
                return true;
            }
        }

        return false;
    }
}
