<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/**
 * «الاختصار واللون» of a teacher (null = default: the suggested abbreviation / the timetable's colour wheel).
 * The teacher's page owns the value; the timetable's context menu calls the same endpoint.
 */
final readonly class UpdateTeacherAppearanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public ?string $abbreviation,
        public ?int $colorHue,
        public ?string $idempotencyKey,
    ) {}
}
