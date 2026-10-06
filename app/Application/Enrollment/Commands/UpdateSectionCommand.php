<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateSectionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $name,
        public ?int $capacity,
        /** رائد الصف — null clears it. */
        public ?int $homeroomTeacherId,
        public ?string $idempotencyKey,
    ) {}
}
