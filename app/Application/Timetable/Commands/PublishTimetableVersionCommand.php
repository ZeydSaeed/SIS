<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «نشر»: an approved version becomes the published timetable from `effectiveFrom`; the previously published
 * one is superseded the day before (it keeps governing its own past dates). Published entries never change.
 */
final readonly class PublishTimetableVersionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
        public string $effectiveFrom,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
