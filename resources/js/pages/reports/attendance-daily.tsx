import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { FileBarChart } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
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

export default function AttendanceDailyReport({ summary, filters }: PageProps) {
    const i18n = t();
    const [sectionId, setSectionId] = useState(filters.section_id?.toString() ?? '');
    const [date, setDate] = useState(filters.date ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.reports.title, href: '/reports' },
        { title: i18n.reports.dailyAttendanceBreadcrumb, href: '/reports/attendance-daily' },
    ];

    const columns: DataTableColumn<SummaryRow>[] = [
        {
            id: 'attendance_date',
            header: i18n.attendance.date,
            cell: (row) => <span dir="ltr">{row.attendance_date}</span>,
        },
        {
            id: 'section_id',
            header: i18n.attendance.section,
            cell: (row) => <span dir="ltr">{row.section_id}</span>,
        },
        {
            id: 'present',
            header: i18n.attendance.present,
            cell: (row) => <span dir="ltr">{row.present_count}</span>,
        },
        {
            id: 'absent',
            header: i18n.attendance.absent,
            cell: (row) => <span dir="ltr">{row.absent_count}</span>,
            hideOnMobile: true,
        },
        {
            id: 'late',
            header: i18n.attendance.late,
            cell: (row) => <span dir="ltr">{row.late_count}</span>,
            hideOnMobile: true,
        },
        {
            id: 'total',
            header: i18n.common.total,
            cell: (row) => <span dir="ltr">{row.total_students}</span>,
            hideOnMobile: true,
        },
    ];

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
            <Head title={i18n.reports.dailyHead} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.reports.dailyHead}
                    description={i18n.reports.dailySummaryDesc}
                    icon={<FileBarChart className="size-6" aria-hidden />}
                />
                <Link href="/reports" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    {i18n.common.backToReports}
                </Link>
                <form
                    onSubmit={onFilter}
                    className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    aria-label={i18n.common.filterDailyAttendance}
                >
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.reports.sectionId}</span>
                        <input
                            type="number"
                            min={1}
                            value={sectionId}
                            onChange={(e) => setSectionId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.attendance.date}</span>
                        <input
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.common.dateFrom}</span>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                        />
                    </label>
                    <label className="flex flex-col gap-1 text-sm">
                        <span>{i18n.common.dateTo}</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                        />
                    </label>
                    <button
                        type="submit"
                        className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm sm:col-span-2 lg:col-span-4 lg:w-fit"
                    >
                        {i18n.common.runReport}
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={summary.data}
                    rowKey={(row) => `${row.section_id}-${row.attendance_date}`}
                    emptyTitle={i18n.reports.noSummaryTitle}
                    emptyDescription={i18n.reports.noSummaryDesc}
                    caption={i18n.reports.dailyCaption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">
                                <span dir="ltr">{row.attendance_date}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.attendance.section}{' '}
                                <span dir="ltr">{row.section_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.attendance.present}{' '}
                                <span dir="ltr">{row.present_count}</span> · {i18n.attendance.absent}{' '}
                                <span dir="ltr">{row.absent_count}</span>
                            </div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
