<?php

namespace App\Http\Controllers\Intelligence;

use App\Application\Intelligence\Commands\ApproveRecommendationCommand;
use App\Application\Intelligence\Commands\ApproveRecommendationHandler;
use App\Application\Intelligence\Commands\RejectRecommendationCommand;
use App\Application\Intelligence\Commands\RejectRecommendationHandler;
use App\Application\Intelligence\Queries\GetRecommendationHandler;
use App\Application\Intelligence\Queries\GetRecommendationQuery;
use App\Application\Intelligence\Queries\ListRecommendationsHandler;
use App\Application\Intelligence\Queries\ListRecommendationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Intelligence\ApproveRecommendationRequest;
use App\Http\Requests\Intelligence\RejectRecommendationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    public function index(Request $request, ListRecommendationsHandler $handler): Response
    {
        $status = $request->string('status')->toString() ?: 'pending';

        $page = $handler->handle(new ListRecommendationsQuery(status: $status));

        return Inertia::render('intelligence/recommendations/index', $page->toInertiaPayload());
    }

    public function show(int $recommendation, GetRecommendationHandler $handler): Response
    {
        $detail = $handler->handle(new GetRecommendationQuery($recommendation));

        return Inertia::render('intelligence/recommendations/show', [
            'recommendation' => $detail->toArray(),
        ]);
    }

    public function approve(
        ApproveRecommendationRequest $request,
        int $recommendation,
        ApproveRecommendationHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ApproveRecommendationCommand(
            recommendationId: $recommendation,
            userId: (int) $request->user()->id,
            reason: $request->validated('reason'),
        ));

        return redirect()
            ->route('intelligence.recommendations.show', $recommendation)
            ->with('success', $result->optimizationEventCode
                ? __('Recommendation approved and optimization :code started.', ['code' => $result->optimizationEventCode])
                : __('Recommendation approved. Manual execution may be required for this action type.'));
    }

    public function reject(
        RejectRecommendationRequest $request,
        int $recommendation,
        RejectRecommendationHandler $handler,
    ): RedirectResponse {
        $handler->handle(new RejectRecommendationCommand(
            recommendationId: $recommendation,
            userId: (int) $request->user()->id,
            reason: $request->validated('reason'),
            choseInstead: $request->validated('chose_instead'),
        ));

        return redirect()
            ->route('intelligence.recommendations.index')
            ->with('success', __('Recommendation rejected.'));
    }
}
