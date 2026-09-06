<?php

namespace App\Application\Intelligence\Commands;

use App\Application\Contracts\Command;

final readonly class RejectRecommendationCommand implements Command
{
    public function __construct(
        public int $recommendationId,
        public int $userId,
        public string $reason,
        public ?string $choseInstead = null,
    ) {}
}
