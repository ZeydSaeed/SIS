<?php

namespace App\Domain\Student\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * The student's placement is stored as ids only (branch / department / grade level):
 * a name that does not match the school's catalog cannot be saved.
 */
final class InvalidStudentPlacementException extends SisDomainException
{
    public static function unknownDepartment(string $name): self
    {
        return new self(
            "القسم «{$name}» غير موجود في فروع المدرسة.",
            'student.placement.department_unknown',
        );
    }

    public static function departmentOutsideBranch(string $name): self
    {
        return new self(
            "القسم «{$name}» لا يتبع الفرع المختار.",
            'student.placement.department_branch_mismatch',
        );
    }

    public static function unknownGradeLevel(string $name): self
    {
        return new self(
            "الصف «{$name}» غير موجود في المدرسة.",
            'student.placement.grade_unknown',
        );
    }

    public static function gradeLockedByEnrollment(): self
    {
        return new self(
            'الطالب مسجّل في صف وشعبة؛ غيّر الصف من صفحة التسجيل (تغيير التسكين).',
            'student.placement.grade_locked',
        );
    }
}
