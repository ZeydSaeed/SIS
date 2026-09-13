<?php

namespace App\Application\Curriculum\Queries;

final readonly class ListSubjectPrerequisitesQuery
{
    public function __construct(
        public int $subjectId,
    ) {}
}
