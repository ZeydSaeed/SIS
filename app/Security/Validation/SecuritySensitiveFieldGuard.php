<?php

namespace App\Security\Validation;

final class SecuritySensitiveFieldGuard
{
    /** @var list<string> */
    public const PROHIBITED_REQUEST_FIELDS = [
        'role',
        'roles',
        'permissions',
        'permission_ids',
        'school_id',
        'enrolled_by',
        'status',
        'effective_to',
        'created_by',
        'approved_by',
        'is_admin',
        'is_super_admin',
        'is_authorized',
        'approved',
        'security_level',
        'security_flags',
        'authorization_flags',
        'privilege',
        'privileges',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function prohibitedRules(): array
    {
        $rules = [];
        foreach (self::PROHIBITED_REQUEST_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
