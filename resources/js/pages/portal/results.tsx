import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { EmptyState } from '@/components/sis/empty-state';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Published = {
    value: string | null;
    version: number;
    incomplete: boolean;
    published_at: string;
} | null;

type Term = {
    term_name: string;
    subject_name: string;
    value: string | null;
    incomplete: boolean;
};

type Row = {
    enrollment_id: number;
    student_name: string;
    student_code: string;
    year_name: string;
    class_name: string;
    annual: Published;
    gpa: Published;
    transcript: Published;
    terms: Term[];
};

type PageProps = { rows: Row[] };

function fill(template: string, params: Record<string, string>): string {
    return Object.entries(params).reduce(
        (text, [key, value]) => text.replace(`:${key}`, value),
        template,
    );
}

/** «نتائجي» — only results the school has published; the server sends nothing else to this page. */
export default function PortalResults({ rows }: PageProps) {
    const i18n = t();
    const p = i18n.portalResults;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: p.title, href: '/portal/results' },
    ];

    const value = (v: Published) =>
        v === null ? (
            <span className="text-muted-foreground">{p.none}</span>
        ) : (
            <span className="flex flex-col">
                <span>
                    <bdi dir="ltr">{v.value ?? p.none}</bdi>
                    {v.incomplete ? (
                        <span className="text-muted-foreground ms-2">
                            ({p.incomplete})
                        </span>
                    ) : null}
                </span>
                <span className="text-muted-foreground text-xs">
                    {fill(p.publishedAt, { at: v.published_at.slice(0, 10) })}
                </span>
            </span>
        );

    const termColumns: DataTableColumn<Term>[] = [
        { id: 'term', header: p.term, cell: (r) => r.term_name },
        { id: 'subject', header: p.subject, cell: (r) => r.subject_name },
        {
            id: 'value',
            header: p.value,
            cell: (r) => (
                <span>
                    <bdi dir="ltr">{r.value ?? p.none}</bdi>
                    {r.incomplete ? (
                        <span className="text-muted-foreground ms-2">
                            ({p.incomplete})
                        </span>
                    ) : null}
                </span>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={p.title} />
            <div className="sis-table-theme sis-ops-hub flex min-w-0 flex-col gap-4 p-4">
                <PageHeader
                    title={p.title}
                    description={p.description}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />

                {rows.length === 0 ? (
                    <EmptyState title={p.emptyTitle} description={p.empty} />
                ) : (
                    rows.map((row) => (
                        <section
                            key={row.enrollment_id}
                            aria-labelledby={`portal-result-${row.enrollment_id}`}
                            className="flex min-w-0 flex-col gap-3 rounded-md border p-4"
                        >
                            <h2
                                id={`portal-result-${row.enrollment_id}`}
                                className="text-base font-semibold"
                            >
                                {row.student_name} — {row.year_name} —{' '}
                                {row.class_name}
                            </h2>
                            <dl className="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        {p.annual}
                                    </dt>
                                    <dd>{value(row.annual)}</dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        {p.gpa}
                                    </dt>
                                    <dd>{value(row.gpa)}</dd>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <dt className="text-muted-foreground">
                                        {p.transcript}
                                    </dt>
                                    <dd>{value(row.transcript)}</dd>
                                </div>
                            </dl>
                            {row.terms.length > 0 ? (
                                <DataTable
                                    columns={termColumns}
                                    rows={row.terms}
                                    rowKey={(r) =>
                                        `${r.term_name}-${r.subject_name}`
                                    }
                                    caption={p.terms}
                                />
                            ) : null}
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
