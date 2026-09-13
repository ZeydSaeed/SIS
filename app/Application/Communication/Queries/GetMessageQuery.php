<?php

namespace App\Application\Communication\Queries;

final readonly class GetMessageQuery
{
    public function __construct(
        public int $schoolId,
        public int $messageId,
    ) {}
}
