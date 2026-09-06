<?php

namespace App\Infrastructure\Persistence\Intelligence;

use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Application\Intelligence\DTOs\RecommendationDetailDTO;
use App\Application\Intelligence\DTOs\RecommendationListItemDTO;
use App\Application\Intelligence\DTOs\RecommendationStatsDTO;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\Recommendation;

final class EloquentRecommendationReadRepository implements RecommendationReadRepositoryInterface
{
    public function paginateByStatus(string $status, int $perPage): array
    {
        $paginator = Recommendation::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $items = collect($paginator->items())
            ->map(fn (Recommendation $rec) => new RecommendationListItemDTO(
                id: (int) $rec->id,
                code: (string) $rec->recommendation_code,
                ruleId: (string) $rec->rule_id,
                riskTier: (int) $rec->risk_tier,
                actionType: (string) $rec->action_type,
                schemaName: $rec->schema_name,
                tableName: $rec->table_name,
                what: (string) $rec->what,
                why: (string) $rec->why,
                confidence: (float) $rec->confidence,
                status: (string) $rec->status,
                createdAt: $rec->created_at?->toIso8601String(),
            ))
            ->all();

        return [
            'items' => $items,
            'pagination' => collect($paginator->toArray())
                ->except('data')
                ->all(),
        ];
    }

    public function findDetailById(int $id): ?RecommendationDetailDTO
    {
        $recommendation = Recommendation::query()->with('detection')->find($id);

        if ($recommendation === null) {
            return null;
        }

        return new RecommendationDetailDTO(
            id: (int) $recommendation->id,
            code: (string) $recommendation->recommendation_code,
            ruleId: (string) $recommendation->rule_id,
            riskTier: (int) $recommendation->risk_tier,
            actionType: (string) $recommendation->action_type,
            schemaName: $recommendation->schema_name,
            tableName: $recommendation->table_name,
            what: (string) $recommendation->what,
            why: (string) $recommendation->why,
            evidence: $recommendation->evidence,
            alternatives: $recommendation->alternatives,
            expectedImpact: $recommendation->expected_impact,
            costAnalysis: $recommendation->cost_analysis,
            rollbackPlan: $recommendation->rollback_plan,
            confidence: (float) $recommendation->confidence,
            contextSimilarity: $recommendation->context_similarity !== null ? (float) $recommendation->context_similarity : null,
            blastRadiusScore: $recommendation->blast_radius_score !== null ? (float) $recommendation->blast_radius_score : null,
            status: (string) $recommendation->status,
            rejectionReason: $recommendation->rejection_reason,
            createdAt: $recommendation->created_at?->toIso8601String(),
            detection: $recommendation->detection ? [
                'title' => $recommendation->detection->title,
                'diagnosis' => $recommendation->detection->diagnosis,
                'evidence' => $recommendation->detection->evidence,
                'detected_at' => $recommendation->detection->detected_at?->toIso8601String(),
            ] : null,
        );
    }

    public function getStats(bool $pgStatAvailable, ?float $databaseSizeMb, ?int $connectionCount): RecommendationStatsDTO
    {
        return new RecommendationStatsDTO(
            pendingCount: Recommendation::query()->where('status', 'pending')->count(),
            approvedCount: Recommendation::query()->where('status', 'approved')->count(),
            executedCount: Recommendation::query()->where('status', 'executed')->count(),
            pgStatAvailable: $pgStatAvailable,
            databaseSizeMb: $databaseSizeMb ?? MonitoringSnapshot::query()->orderByDesc('captured_at')->value('database_size_mb'),
            connectionCount: $connectionCount ?? MonitoringSnapshot::query()->orderByDesc('captured_at')->value('connection_count'),
            intelligenceEnabled: (bool) config('intelligence.enabled'),
        );
    }
}
