import { Head, Link, router } from '@inertiajs/react';
import { Brain, CheckCircle2, XCircle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

type RecommendationRow = {
    id: number;
    code: string;
    rule_id: string | null;
    risk_tier: number;
    action_type: string;
    schema_name: string | null;
    table_name: string | null;
    what: string;
    why: string;
    confidence: string | null;
    status: string;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
};

type PageProps = {
    recommendations: Paginated<RecommendationRow>;
    filters: { status: string };
    stats: {
        pending_count: number;
        approved_count: number;
        executed_count: number;
        pg_stat_available: boolean;
        database_size_mb: string | null;
        connection_count: number | null;
        intelligence_enabled: boolean;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Database Intelligence', href: '/intelligence/recommendations' },
];

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

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'pending') return 'secondary';
    if (status === 'approved' || status === 'executed') return 'default';
    if (status === 'rejected') return 'destructive';

    return 'outline';
}

export default function RecommendationsIndex({
    recommendations,
    filters,
    stats,
}: PageProps) {
    const setStatus = (status: string) => {
        router.get('/intelligence/recommendations', { status }, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Database Intelligence" />

            <div className="flex flex-col gap-6 p-4" dir="rtl">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Brain className="size-8 text-primary" />
                        <div>
                            <h1 className="text-2xl font-semibold">Database Guardian</h1>
                            <p className="text-muted-foreground text-sm">
                                مراجعة توصيات التحسين — Tier 2+ تحتاج موافقة
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge variant={stats.pg_stat_available ? 'default' : 'destructive'}>
                            pg_stat: {stats.pg_stat_available ? 'ON' : 'OFF'}
                        </Badge>
                        <Badge variant="outline">
                            DB: <span dir="ltr">{stats.database_size_mb ?? '—'}</span> MB
                        </Badge>
                        <Badge variant="outline">
                            Pending: <span dir="ltr">{stats.pending_count}</span>
                        </Badge>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {['pending', 'approved', 'executed', 'rejected', 'all'].map((status) => (
                        <Button
                            key={status}
                            variant={filters.status === status ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setStatus(status)}
                        >
                            {status}
                        </Button>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-muted/50">
                            <tr className="text-start">
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Table</th>
                                <th className="px-4 py-3 font-medium">Action</th>
                                <th className="px-4 py-3 font-medium">Risk</th>
                                <th className="px-4 py-3 font-medium">Confidence</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {recommendations.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-muted-foreground px-4 py-8 text-center">
                                        لا توصيات — النظام يراقب تلقائيًا (Tier 0–1)
                                    </td>
                                </tr>
                            ) : (
                                recommendations.data.map((rec) => (
                                    <tr key={rec.id} className="border-t">
                                        <td className="px-4 py-3 font-mono text-xs" dir="ltr">
                                            {rec.code}
                                        </td>
                                        <td className="px-4 py-3" dir="ltr">
                                            {rec.schema_name}.{rec.table_name}
                                        </td>
                                        <td className="px-4 py-3">{rec.action_type}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{riskLabel(rec.risk_tier)}</Badge>
                                        </td>
                                        <td className="px-4 py-3" dir="ltr">
                                            {rec.confidence
                                                ? `${(Number(rec.confidence) * 100).toFixed(0)}%`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={statusVariant(rec.status)}>
                                                {rec.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Button asChild size="sm" variant="ghost">
                                                <Link href={`/intelligence/recommendations/${rec.id}`}>
                                                    Review
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
