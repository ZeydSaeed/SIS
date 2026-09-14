import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { FileBarChart } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type SummaryRow = {
    section_id: number;
    school_id: number;
    academic_year_id: number;
    attendance_date: string;
    total_students: number;
    present_count: number;
    absent_count: number;
    late_count: number;
};

type PageProps = {
    summary: {
        data: SummaryRow[];
    };
    filters: {
        section_id: number | null;
        date: string | null;
        date_from: string | null;
        date_to: string | null;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '/reports' },
    { title: 'Daily attendance', href: '/reports/attendance-daily' },
];

const columns: DataTableColumn<SummaryRow>[] = [
    {
        id: 'attendance_date',
        header: 'Date',
        cell: (row) => row.attendance_date,
    },
    {
        id: 'section_id',
        header: 'Section',
        cell: (row) => row.section_id,
    },
    {
        id: 'present',
        header: 'Present',
        cell: (row) => row.present_count,
    },
    {
        id: 'absent',
        header: 'Absent',
        cell: (row) => row.absent_count,
        hideOnMobile: true,
    },
    {
        id: 'late',
        header: 'Late',
        cell: (row) => row.late_count,
        hideOnMobile: true,
    },
    {
        id: 'total',
        header: 'Total',
        cell: (row) => row.total_students,
        hideOnMobile: true,
    },
];

export default function AttendanceDailyReport({ summary, filters }: PageProps) {
    const [sectionId, setSectionId] = useState(filters.section_id?.toString() ?? '');
    const [date, setDate] = useState(filters.date ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        const parsedSection = Number.parseInt(sectionId, 10);
        router.get(
            '/reports/attendance-daily',
            {
                section_id: Number.isFinite(parsedSection) && parsedSection > 0 ? parsedSection : undefined,
                date: date || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Daily attendance summary" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Daily section summary"
                    description="Aggregated attendance counts by section and date."
                    icon={<FileBarChart className="size-6" aria-hidden />}
                />
                <Link href="/reports" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    Back to reports
                </Link>
                <form
                    onSubmit={onFilter}
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    aria-label="Filter daily attendance summary"
                >
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Section ID</span>
                        <input
                            type="number"
                            min={1}
                            value={sectionId}
                            onChange={(e) => setSectionId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Date</span>
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Date from</span>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>Date to</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <button
                        type="submit"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm sm:col-span-2 lg:col-span-4 lg:w-fit"
                    >
                        Run report
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={summary.data}
                    rowKey={(row) => `${row.section_id}-${row.attendance_date}`}
                    emptyTitle="No summary rows"
                    emptyDescription="Provide section ID and date (or date range) to load the report."
                    caption="Daily section attendance summary"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">{row.attendance_date}</div>
                            <div className="text-sm opacity-80">Section {row.section_id}</div>
                            <div className="text-sm opacity-80">
                                Present {row.present_count} · Absent {row.absent_count}
                            </div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
