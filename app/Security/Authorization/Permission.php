<?php

namespace App\Security\Authorization;

final class Permission
{
    public const STUDENTS_VIEW = 'students.view';

    public const STUDENTS_CREATE = 'students.create';

    public const STUDENTS_UPDATE = 'students.update';

    public const STUDENTS_VIEW_PII = 'students.view_pii';

    public const ENROLLMENT_VIEW = 'enrollment.view';

    public const ENROLLMENT_CREATE = 'enrollment.create';

    public const ENROLLMENT_UPDATE = 'enrollment.update';

    public const ENROLLMENT_CANCEL = 'enrollment.cancel';

    public const GRADES_VIEW = 'grades.view';

    public const GRADES_CREATE = 'grades.create';

    public const GRADES_CORRECT = 'grades.correct';

    public const GRADES_VOID = 'grades.void';

    public const GRADES_FINALIZE = 'grades.finalize';

    public const ATTENDANCE_VIEW = 'attendance.view';

    public const ATTENDANCE_SESSION_CREATE = 'attendance.session.create';

    public const ATTENDANCE_MARK = 'attendance.mark';

    public const ATTENDANCE_CORRECT = 'attendance.correct';

    public const ATTENDANCE_SESSION_CLOSE = 'attendance.session.close';

    public const ATTENDANCE_SESSION_CANCEL = 'attendance.session.cancel';

    public const SECURITY_MANAGE_USERS = 'security.manage_users';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::STUDENTS_VIEW,
            self::STUDENTS_CREATE,
            self::STUDENTS_UPDATE,
            self::STUDENTS_VIEW_PII,
            self::ENROLLMENT_VIEW,
            self::ENROLLMENT_CREATE,
            self::ENROLLMENT_UPDATE,
            self::ENROLLMENT_CANCEL,
            self::GRADES_VIEW,
            self::GRADES_CREATE,
            self::GRADES_CORRECT,
            self::GRADES_VOID,
            self::GRADES_FINALIZE,
            self::ATTENDANCE_VIEW,
            self::ATTENDANCE_SESSION_CREATE,
            self::ATTENDANCE_MARK,
            self::ATTENDANCE_CORRECT,
            self::ATTENDANCE_SESSION_CLOSE,
            self::ATTENDANCE_SESSION_CANCEL,
            self::SECURITY_MANAGE_USERS,
        ];
    }
}
