<?php

namespace App\Domain\Documents\Support;

final class DocumentEntityTypes
{
    public const ALLOWED = [
        'student',
        'teacher',
        'enrollment',
        'certificate',
        'qualification',
        // «الإجازات»: attachments of a staff leave request (medical reports, orders).
        'leave_request',
    ];

    /**
     * Owned by one module, which applies its own access rules (e.g. medical leave): the generic documents API
     * neither writes nor reads them — only the owning module's endpoints do.
     */
    public const MODULE_OWNED = [
        'leave_request',
    ];

    public static function isAllowed(string $entityType): bool
    {
        return in_array(strtolower(trim($entityType)), self::ALLOWED, true);
    }

    /** Usable through the generic documents API (register / upload / list / show / download). */
    public static function isGeneric(string $entityType): bool
    {
        return self::isAllowed($entityType) && ! in_array(strtolower(trim($entityType)), self::MODULE_OWNED, true);
    }

    /** @return list<string> */
    public static function generic(): array
    {
        return array_values(array_diff(self::ALLOWED, self::MODULE_OWNED));
    }
}
