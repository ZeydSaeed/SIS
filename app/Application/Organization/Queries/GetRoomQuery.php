<?php

namespace App\Application\Organization\Queries;

final readonly class GetRoomQuery
{
    public function __construct(
        public int $schoolId,
        public int $roomId,
    ) {}
}
