<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** The author changes the content of their draft (DEC-057: only a draft changes). */
final readonly class UpdateQuestionDraftCommand implements Command
{
    /** @param  array<string, mixed>  $content */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $versionId,
        public string $type,
        public string $stem,
        public string $maxMark,
        public array $content,
        public int $actorId,
        public bool $schoolWide,
        public ?string $idempotencyKey,
    ) {}
}
