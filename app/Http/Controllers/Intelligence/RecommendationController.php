<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Intelligence\ApproveRecommendationRequest;
use App\Http\Requests\Intelligence\RejectRecommendationRequest;
use App\Intelligence\Governance\ApprovalGate;
use App\Intelligence\Models\MonitoringSnapshot;
use App\Intelligence\Models\Recommendation;
use App\Intelligence\Monitoring\PgStatStatementsCollector;
use App\Intelligence\Optimization\SafeAutoExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function index(Request $request, PgStatStatementsCollector $pgStat): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $recommendations = Recommendation::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Recommendation $rec) => [
                'id' => $rec->id,
                'code' => $rec->recommendation_code,
                'rule_id' => $rec->rule_id,
                'risk_tier' => $rec->risk_tier,
                'action_type' => $rec->action_type,
                'schema_name' => $rec->schema_name,
                'table_name' => $rec->table_name,
                'what' => $rec->what,
                'why' => $rec->why,
                'confidence' => $rec->confidence,
                'status' => $rec->status,
                'created_at' => $rec->created_at?->toIso8601String(),
            ]);

        $lastSnapshot = MonitoringSnapshot::query()->orderByDesc('captured_at')->first();

        return Inertia::render('intelligence/recommendations/index', [
            'recommendations' => $recommendations,
            'filters' => ['status' => $status],
            'stats' => [
                'pending_count' => Recommendation::query()->where('status', 'pending')->count(),
                'approved_count' => Recommendation::query()->where('status', 'approved')->count(),
                'executed_count' => Recommendation::query()->where('status', 'executed')->count(),
                'pg_stat_available' => $pgStat->extensionAvailable(),
                'database_size_mb' => $lastSnapshot?->database_size_mb,
                'connection_count' => $lastSnapshot?->connection_count,
                'intelligence_enabled' => config('intelligence.enabled'),
            ],
        ]);
    }

    public function show(Recommendation $recommendation): Response
    {
        $recommendation->load('detection');

        return Inertia::render('intelligence/recommendations/show', [
            'recommendation' => [
                'id' => $recommendation->id,
                'code' => $recommendation->recommendation_code,
                'rule_id' => $recommendation->rule_id,
                'risk_tier' => $recommendation->risk_tier,
                'action_type' => $recommendation->action_type,
                'schema_name' => $recommendation->schema_name,
                'table_name' => $recommendation->table_name,
                'what' => $recommendation->what,
                'why' => $recommendation->why,
                'evidence' => $recommendation->evidence,
                'alternatives' => $recommendation->alternatives,
                'expected_impact' => $recommendation->expected_impact,
                'cost_analysis' => $recommendation->cost_analysis,
                'rollback_plan' => $recommendation->rollback_plan,
                'confidence' => $recommendation->confidence,
                'context_similarity' => $recommendation->context_similarity,
                'blast_radius_score' => $recommendation->blast_radius_score,
                'status' => $recommendation->status,
                'rejection_reason' => $recommendation->rejection_reason,
                'created_at' => $recommendation->created_at?->toIso8601String(),
                'detection' => $recommendation->detection ? [
                    'title' => $recommendation->detection->title,
                    'diagnosis' => $recommendation->detection->diagnosis,
                    'evidence' => $recommendation->detection->evidence,
                    'detected_at' => $recommendation->detection->detected_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    public function approve(
        ApproveRecommendationRequest $request,
        Recommendation $recommendation,
        ApprovalGate $approvalGate,
        SafeAutoExecutor $executor,
    ): RedirectResponse {
        $approvalGate->approve(
            $recommendation,
            $request->user()->id,
            $request->validated('reason'),
        );

        $event = $executor->executeApproved($recommendation->fresh());

        return redirect()
            ->route('intelligence.recommendations.show', $recommendation)
            ->with('success', $event
                ? __('Recommendation approved and optimization :code started.', ['code' => $event->event_code])
                : __('Recommendation approved. Manual execution may be required for this action type.'));
    }

    public function reject(
        RejectRecommendationRequest $request,
        Recommendation $recommendation,
        ApprovalGate $approvalGate,
    ): RedirectResponse {
        $approvalGate->reject(
            $recommendation,
            $request->user()->id,
            $request->validated('reason'),
            $request->validated('chose_instead'),
        );

        return redirect()
            ->route('intelligence.recommendations.index')
            ->with('success', __('Recommendation rejected.'));
    }
}
