<?php

namespace App\Application\Intelligence\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Application\Intelligence\DTOs\RecommendationDetailDTO;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GetRecommendationHandler implements QueryHandler
{
    public function __construct(
        private readonly RecommendationReadRepositoryInterface $recommendations,
    ) {}

    public function handle(Query $query): RecommendationDetailDTO
    {
        assert($query instanceof GetRecommendationQuery);

        $detail = $this->recommendations->findDetailById($query->recommendationId);

        if ($detail === null) {
            throw new NotFoundHttpException('Recommendation not found.');
        }

        return $detail;
    }
}
