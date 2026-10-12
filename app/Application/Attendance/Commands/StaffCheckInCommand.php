<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;

/**
 * A teacher records their own arrival (or departure) with proof: the school's current short-lived code typed in or
 * scanned from its QR, or an NFC station tag. Signing in to the SIS alone is never accepted as proof of presence.
 */
final readonly class StaffCheckInCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $teacherId,
        public string $code,
        public int $actorUserId,
        public string $idempotencyKey,
        /** code · qr · nfc */
        public string $via = 'code',
    ) {}
}
