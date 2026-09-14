import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type ResultRow = {
    school_id: number;
    enrollment_id: number;
    academic_year_id: number;
    term_result_id: number;
    term_id: number;
    subject_id: number;
    weighted_total: string | null;
    pass_fail: number | null;
    incomplete: boolean;
};

type PageProps = {
    results: {
        data: ResultRow[];
    };
    filters: {
        academic_year_id: number | null;
        enrollment_id: number | null;
    };
};

export default function ResultsIndex({ results, filters }: PageProps) {
    const i18n = t();
    const [enrollmentId, setEnrollmentId] = useState(filters.enrollment_id?.toString() ?? '');

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.modules.results, href: '/results' }];

    const columns: DataTableColumn<ResultRow>[] = [
        {
            id: 'term_id',
            header: i18n.results.term,
            cell: (row) => <span dir="ltr">{row.term_id}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) => <span dir="ltr">{row.subject_id}</span>,
        },
        {
            id: 'weighted_total',
            header: i18n.results.weightedTotal,
            cell: (row) => <span dir="ltr">{row.weighted_total ?? '—'}</span>,
        },
        {
            id: 'pass_fail',
            header: i18n.results.passFail,
            cell: (row) => <span dir="ltr">{row.pass_fail === null ? '—' : row.pass_fail}</span>,
        },
        {
            id: 'incomplete',
            header: i18n.results.incomplete,
            cell: (row) => (row.incomplete ? i18n.common.yes : i18n.common.no),
            hideOnMobile: true,
        },
    ];

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        const parsed = Number.parseInt(enrollmentId, 10);
        router.get(
            '/results',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                enrollment_id: Number.isFinite(parsed) && parsed > 0 ? parsed : undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.results.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.results.title}
                    description={i18n.results.description}
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <p>
                    <a href="/results/show" className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.summaryLink}
                    </a>
                    <a href="/results/transcript" className="sis-ops-hub__link ms-2 px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.transcriptLink}
                    </a>
                </p>
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label={i18n.common.filterTermResults}
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>{i18n.results.enrollmentId}</span>
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                            placeholder={i18n.common.required}
                            dir="ltr"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.loadResults}
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={results.data}
                    rowKey={(row) => row.term_result_id}
                    emptyTitle={
                        filters.enrollment_id ? i18n.results.noResults : i18n.results.selectEnrollment
                    }
                    emptyDescription={
                        filters.enrollment_id ? i18n.results.noResultsDesc : i18n.results.selectEnrollmentDesc
                    }
                    caption={i18n.results.termCaption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3">
                            <div className="font-semibold">
                                {i18n.results.term}{' '}
                                <span dir="ltr">{row.term_id}</span> · {i18n.results.subject}{' '}
                                <span dir="ltr">{row.subject_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.results.totalShort}{' '}
                                <span dir="ltr">{row.weighted_total ?? '—'}</span>
                            </div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
