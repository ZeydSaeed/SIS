<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

/**
 * «أنواع الغرف»: creates one of the school's own room types (typeId null) or edits one. System types are shared
 * and read-only; `supportsPractical` null = the kind's default (labs and workshops).
 */
final readonly class SaveRoomTypeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $typeId,
        public ?string $code,
        public string $name,
        public ?string $abbreviation,
        public ?int $colorHue,
        public int $kind,
        public ?bool $supportsPractical,
        public ?string $idempotencyKey,
    ) {}
}
