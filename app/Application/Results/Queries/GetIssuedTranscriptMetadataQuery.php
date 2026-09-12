<?php

namespace App\Application\Results\Queries;

use App\Application\Contracts\Query;

final readonly class GetIssuedTranscriptMetadataQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
    ) {}
}
