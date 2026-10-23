import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { ResultsStudentPicker, type ResultsPicker } from '@/components/results/results-student-picker';
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
    picker: ResultsPicker;
    names: { terms: Record<number, string>; subjects: Record<number, string> };
};

export default function ResultsIndex({ results, filters, picker, names }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.modules.results, href: '/results' }];

    const columns: DataTableColumn<ResultRow>[] = [
        {
            id: 'term_id',
            header: i18n.results.term,
            cell: (row) => names.terms[row.term_id] ?? <span dir="ltr">{row.term_id}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) => names.subjects[row.subject_id] ?? <span dir="ltr">{row.subject_id}</span>,
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
                    <a href={`/results/show?academic_year_id=${filters.academic_year_id ?? ''}&enrollment_id=${filters.enrollment_id ?? ''}`} className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.summaryLink}
                    </a>
                    <a href={`/results/transcript?academic_year_id=${filters.academic_year_id ?? ''}&enrollment_id=${filters.enrollment_id ?? ''}`} className="sis-ops-hub__link ms-2 px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.transcriptLink}
                    </a>
                    <a href={`/results/sheet?academic_year_id=${filters.academic_year_id ?? ''}`} className="sis-ops-hub__link ms-2 px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.sheet.title}
                    </a>
                    <a href={`/results/year-sheet?academic_year_id=${filters.academic_year_id ?? ''}`} className="sis-ops-hub__link ms-2 px-3 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.results.yearSheet.title}
                    </a>
                </p>
                <OpsYearFilter
                    action="/results"
                    academicYearId={filters.academic_year_id}
                    extraParams={{ enrollment_id: filters.enrollment_id ?? undefined }}
                />
                <ResultsStudentPicker path="/results" academicYearId={filters.academic_year_id} picker={picker} />
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
                                {names.terms[row.term_id] ?? row.term_id} ·{' '}
                                {names.subjects[row.subject_id] ?? row.subject_id}
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
