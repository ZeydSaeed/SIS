<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;

/** «الاختصار واللون» of a class (الصف) or section (الشعبة) of the school (null = default). */
final readonly class UpdateStructureAppearanceCommand implements Command
{
    public const TARGETS = ['class', 'section'];

    public function __construct(
        public int $schoolId,
        public string $target,
        public int $id,
        public ?string $abbreviation,
        public ?int $colorHue,
        public ?string $idempotencyKey,
    ) {}
}
