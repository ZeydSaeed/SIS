<?php

namespace App\Application\Bank\Commands;

use App\Application\Contracts\Command;

/** A new question of a subject with its first version as a draft of the actor. */
final readonly class CreateQuestionCommand implements Command
{
    /** @param  array<string, mixed>  $content  options/correct, answer or guidance (QuestionContent::fromInput) */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $subjectId,
        public string $type,
        public string $stem,
        public string $maxMark,
        public array $content,
        public int $actorId,
        public bool $schoolWide,
        public ?string $idempotencyKey,
    ) {}
}
