<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Data\CurriculumLinePlacement;
use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * Teachers of a curriculum subject line: one main teacher (or the only teacher), then one or more assistants.
 * An assistant needs the main teacher; the main teacher is replaced (not added twice) and cannot leave the line
 * while assistants remain on it.
 */
final class CurriculumTeacherGuard
{
    public function assignError(
        CurriculumLinePlacement $line,
        int $role,
        bool $mainExists,
        bool $teacherOnLine,
        bool $replaceMain,
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
        if ($teacherOnLine) {
            return 'teachers.curriculum_teacher_exists';
        }
        if ($role === TeachingRole::Main && $mainExists && ! $replaceMain) {
            return 'teachers.curriculum_main_exists';
        }
        if ($role === TeachingRole::Assistant && ! $mainExists) {
            return 'teachers.curriculum_assistant_needs_main';
        }

        return null;
    }

    public function endError(int $role, int $activeAssistants): ?string
    {
        if ($role === TeachingRole::Main && $activeAssistants > 0) {
            return 'teachers.curriculum_main_has_assistants';
        }

        return null;
    }
}
