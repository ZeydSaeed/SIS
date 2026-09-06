<?php

namespace App\Application\Intelligence\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Intelligence\Contracts\RecommendationCommandPort;
use App\Application\Intelligence\Results\ApproveRecommendationResult;

final class ApproveRecommendationHandler implements CommandHandler
{
    public function __construct(
        private readonly RecommendationCommandPort $recommendations,
    ) {}

    public function handle(Command $command): ApproveRecommendationResult
    {
        assert($command instanceof ApproveRecommendationCommand);

        return $this->recommendations->approve(
            $command->recommendationId,
            $command->userId,
            $command->reason,
        );
    }
}
