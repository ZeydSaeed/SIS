<?php

namespace App\Security\Authorization;

final class Permission
{
    public const STUDENTS_VIEW = 'students.view';

    public const STUDENTS_CREATE = 'students.create';

    public const STUDENTS_UPDATE = 'students.update';

    public const STUDENTS_VIEW_PII = 'students.view_pii';

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
        ];
    }
}
