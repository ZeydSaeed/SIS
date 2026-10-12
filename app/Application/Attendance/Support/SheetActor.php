<?php

namespace App\Application\Attendance\Support;

/**
 * Who is acting on a sheet: the user, the teacher their account is linked to (if any), and whether they hold the
 * reviewer permission that lets them act on behalf of any teacher of the school (always recorded as such).
 * Resolved by the HTTP layer from the authenticated user — never from request input.
 */
final readonly class SheetActor
{
    public function __construct(
        public int $userId,
        public ?int $teacherId,
        public bool $isReviewer,
    ) {}
}
