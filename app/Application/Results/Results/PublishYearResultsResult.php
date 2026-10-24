<?php

namespace App\Application\Results\Results;

final readonly class PublishYearResultsResult
{
    public function __construct(
        public int $publicationId,
        public int $participants,
    ) {}
}
