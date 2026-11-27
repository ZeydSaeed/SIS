<?php

namespace App\Application\Bank\Queries;

/** `$subjectIds` null = school-wide; otherwise only these subjects (the teacher's own). */
final readonly class ListQuestionsQuery
{
    /** @param  list<int>|null  $subjectIds */
    public function __construct(
        public int $schoolId,
        public ?array $subjectIds,
        public ?int $subjectId,
        public ?string $status,
        public ?string $search,
        public int $page,
        public int $perPage = 25,
    ) {}
}
