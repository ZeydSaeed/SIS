<?php

namespace App\Application\Results\Commands;

use App\Application\Contracts\Command;

/**
 * Publish the official annual results, year GPAs, term results (of one term + subject) or issued transcripts of a class
 * (or section) to students.
 */
final readonly class PublishYearResultsCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $classId,
        public ?int $sectionId,
        public string $kind,
        public ?int $actorUserId,
        public ?string $correlationId,
        public ?int $termId = null,
        public ?int $subjectId = null,
    ) {}
}
