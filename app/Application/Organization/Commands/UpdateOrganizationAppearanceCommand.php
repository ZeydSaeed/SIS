<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

/**
 * «الاختصار واللون» of a branch, department, room or room type of the school (null = the default).
 * Used by the owning pages and by the timetable's context menu — the value lives on the owning row only.
 */
final readonly class UpdateOrganizationAppearanceCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $target,
        public int $id,
        public ?string $abbreviation,
        public ?int $colorHue,
        public ?string $idempotencyKey,
    ) {}
}
