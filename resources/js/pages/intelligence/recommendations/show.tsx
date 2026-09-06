import { Form, Head, Link } from '@inertiajs/react';
import { Brain, CheckCircle2, XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { BreadcrumbItem } from '@/types';

type RecommendationDetail = {
    id: number;
    code: string;
    rule_id: string | null;
    risk_tier: number;
    action_type: string;
    schema_name: string | null;
    table_name: string | null;
    what: string;
    why: string;
    evidence: Record<string, unknown> | null;
    alternatives: { option: string; status: string }[] | null;
    expected_impact: Record<string, unknown> | null;
    cost_analysis: Record<string, unknown> | null;
    rollback_plan: string | null;
    confidence: string | null;
    context_similarity: string | null;
    blast_radius_score: number | null;
    status: string;
    rejection_reason: string | null;
    created_at: string | null;
    detection: {
        title: string;
        diagnosis: string | null;
        evidence: Record<string, unknown> | null;
        detected_at: string | null;
    } | null;
};

type PageProps = {
    recommendation: RecommendationDetail;
};

function riskLabel(tier: number): string {
    const labels: Record<number, string> = {
        0: 'Observe',
        1: 'Safe Auto',
        2: 'Human Review',
        3: 'ADR + DBA',
        4: 'Forbidden',
    };

    return labels[tier] ?? `Tier ${tier}`;
}

export default function RecommendationShow({ recommendation }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Database Intelligence', href: '/intelligence/recommendations' },
        { title: recommendation.code, href: `/intelligence/recommendations/${recommendation.id}` },
    ];

    const canDecide = recommendation.status === 'pending' && recommendation.risk_tier >= 2;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Recommendation ${recommendation.code}`} />

            <div className="flex flex-col gap-6 p-4" dir="rtl">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Brain className="size-8 text-primary" />
                        <div>
                            <h1 className="font-mono text-xl" dir="ltr">
                                {recommendation.code}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                {recommendation.rule_id} ·{' '}
                                <span dir="ltr">
                                    {recommendation.schema_name}.{recommendation.table_name}
                                </span>
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{riskLabel(recommendation.risk_tier)}</Badge>
                        <Badge>{recommendation.status}</Badge>
                        {recommendation.confidence && (
                            <Badge variant="secondary" dir="ltr">
                                Confidence: {(Number(recommendation.confidence) * 100).toFixed(0)}%
                            </Badge>
                        )}
                    </div>
                </div>

                <section className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-2 font-semibold">What</h2>
                        <p className="text-sm">{recommendation.what}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-2 font-semibold">Why</h2>
                        <p className="text-sm">{recommendation.why}</p>
                    </div>
                </section>

                {recommendation.detection && (
                    <section className="rounded-xl border p-4">
                        <h2 className="mb-2 font-semibold">Detection</h2>
                        <p className="mb-2 text-sm">{recommendation.detection.title}</p>
                        <pre className="bg-muted overflow-x-auto rounded-lg p-3 text-xs" dir="ltr">
                            {JSON.stringify(recommendation.detection.evidence, null, 2)}
                        </pre>
                    </section>
                )}

                {recommendation.rollback_plan && (
                    <section className="rounded-xl border p-4">
                        <h2 className="mb-2 font-semibold">Rollback Plan</h2>
                        <p className="text-sm" dir="ltr">
                            {recommendation.rollback_plan}
                        </p>
                    </section>
                )}

                {canDecide && (
                    <section className="grid gap-6 md:grid-cols-2">
                        <Form
                            action={`/intelligence/recommendations/${recommendation.id}/approve`}
                            method="post"
                            className="space-y-4 rounded-xl border border-green-500/30 p-4"
                        >
                            <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                                <CheckCircle2 className="size-5" />
                                <h2 className="font-semibold">Approve</h2>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="approve-reason">Reason (optional)</Label>
                                <textarea
                                    id="approve-reason"
                                    name="reason"
                                    rows={3}
                                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                />
                            </div>
                            <Button type="submit">Approve & Execute</Button>
                        </Form>

                        <Form
                            action={`/intelligence/recommendations/${recommendation.id}/reject`}
                            method="post"
                            className="space-y-4 rounded-xl border border-red-500/30 p-4"
                        >
                            <div className="flex items-center gap-2 text-red-700 dark:text-red-400">
                                <XCircle className="size-5" />
                                <h2 className="font-semibold">Reject</h2>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="reject-reason">Reason (required)</Label>
                                <textarea
                                    id="reject-reason"
                                    name="reason"
                                    rows={3}
                                    required
                                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="chose-instead">Alternative chosen</Label>
                                <textarea
                                    id="chose-instead"
                                    name="chose_instead"
                                    rows={2}
                                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[60px] w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                />
                            </div>
                            <Button type="submit" variant="destructive">
                                Reject
                            </Button>
                        </Form>
                    </section>
                )}

                <Button asChild variant="outline" className="w-fit">
                    <Link href="/intelligence/recommendations">Back to list</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
