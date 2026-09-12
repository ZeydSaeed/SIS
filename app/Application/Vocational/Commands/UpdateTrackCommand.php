<?php

namespace App\Application\Vocational\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateTrackCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $trackId,
        public string $code,
        public string $name,
        public string $idempotencyKey,
        public ?string $correlationId = null,
    ) {}
}
