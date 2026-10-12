<?php

namespace App\Domain\Attendance\Data;

/** One student expected in a lesson: an enrollment active in the section (and group) on the lesson date. */
final readonly class RosterEntry
{
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
    ) {}
}
