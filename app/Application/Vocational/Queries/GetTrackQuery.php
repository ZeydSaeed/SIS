<?php

namespace App\Application\Vocational\Queries;

final readonly class GetTrackQuery
{
    public function __construct(
        public int $schoolId,
        public int $trackId,
    ) {}
}
