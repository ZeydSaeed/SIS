<?php

namespace App\Application\Intelligence\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Intelligence\Contracts\RecommendationCommandPort;

final class RejectRecommendationHandler implements CommandHandler
{
    public function __construct(
        private readonly RecommendationCommandPort $recommendations,
    ) {}

    public function handle(Command $command): void
    {
        assert($command instanceof RejectRecommendationCommand);

        $this->recommendations->reject(
            $command->recommendationId,
            $command->userId,
            $command->reason,
            $command->choseInstead,
        );
    }
}
