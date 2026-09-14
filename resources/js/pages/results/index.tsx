import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ScrollText } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Results', href: '/results' }];

const columns: DataTableColumn<ResultRow>[] = [
    {
        id: 'term_id',
        header: 'Term',
        cell: (row) => row.term_id,
    },
    {
        id: 'subject_id',
        header: 'Subject',
        cell: (row) => row.subject_id,
    },
    {
        id: 'weighted_total',
        header: 'Weighted total',
        cell: (row) => row.weighted_total ?? '—',
    },
    {
        id: 'pass_fail',
        header: 'Pass / Fail',
        cell: (row) => (row.pass_fail === null ? '—' : row.pass_fail),
    },
    {
        id: 'incomplete',
        header: 'Incomplete',
        cell: (row) => (row.incomplete ? 'Yes' : 'No'),
        hideOnMobile: true,
    },
];

export default function ResultsIndex({ results, filters }: PageProps) {
    const [enrollmentId, setEnrollmentId] = useState(
        filters.enrollment_id?.toString() ?? '',
    );

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
            <Head title="Official term results" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Official term results"
                    description="Read official term subject results for a student enrollment."
                    icon={<ScrollText className="size-6" aria-hidden />}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label="Filter term results"
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>Enrollment ID</span>
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            value={enrollmentId}
                            onChange={(e) => setEnrollmentId(e.target.value)}
                            placeholder="Required"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        Load results
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={results.data}
                    rowKey={(row) => row.term_result_id}
                    emptyTitle={
                        filters.enrollment_id
                            ? 'No official term results'
                            : 'Select an enrollment'
                    }
                    emptyDescription={
                        filters.enrollment_id
                            ? 'No official rows for this enrollment and year.'
                            : 'Enter an enrollment ID to load official term results.'
                    }
                    caption="Official term results"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3">
                            <div className="font-semibold">
                                Term {row.term_id} · Subject {row.subject_id}
                            </div>
                            <div className="text-sm opacity-80">
                                Total {row.weighted_total ?? '—'}
                            </div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
