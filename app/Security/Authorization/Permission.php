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
        ];
    }
}
