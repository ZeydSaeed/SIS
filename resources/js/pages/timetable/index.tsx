import { Head, router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type ScheduleRow = {
    id: number;
    section_id: number;
    academic_year_id: number;
    day_of_week: number;
    period_id: number;
    subject_id: number;
    teacher_id: number;
    room_id: number | null;
    lifecycle_status: number;
};

type PageProps = {
    schedules: {
        data: ScheduleRow[];
        meta: {
            pagination: {
                page: number;
                per_page: number;
                total: number;
                total_pages: number;
            };
        };
    };
    filters: {
        academic_year_id: number | null;
        page: number;
        per_page: number;
    };
};

export default function TimetableIndex({ schedules, filters }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.timetable.title, href: '/timetable' }];

    const columns: DataTableColumn<ScheduleRow>[] = [
        {
            id: 'day_of_week',
            header: i18n.timetable.day,
            cell: (row) => <span dir="ltr">{row.day_of_week}</span>,
        },
        {
            id: 'period_id',
            header: i18n.attendance.period,
            cell: (row) => <span dir="ltr">{row.period_id}</span>,
        },
        {
            id: 'section_id',
            header: i18n.attendance.section,
            cell: (row) => <span dir="ltr">{row.section_id}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) => <span dir="ltr">{row.subject_id}</span>,
            hideOnMobile: true,
        },
        {
            id: 'teacher_id',
            header: i18n.attendance.teacher,
            cell: (row) => <span dir="ltr">{row.teacher_id}</span>,
            hideOnMobile: true,
        },
        {
            id: 'room_id',
            header: i18n.timetable.room,
            cell: (row) => <span dir="ltr">{row.room_id ?? '—'}</span>,
            hideOnMobile: true,
        },
    ];

    const pagination = schedules.meta.pagination;
    const goPage = (page: number) => {
        router.get(
            '/timetable',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                page,
                per_page: filters.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.timetable.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.timetable.title}
                    description={i18n.timetable.description}
                    icon={<CalendarRange className="size-6" aria-hidden />}
                />
                <DataTable
                    columns={columns}
                    rows={schedules.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.timetable.emptyTitle}
                    emptyDescription={i18n.timetable.emptyDesc}
                    caption={i18n.timetable.schedulesCaption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_35%,white)] p-3">
                            <div className="font-semibold">
                                {i18n.common.day}{' '}
                                <span dir="ltr">{row.day_of_week}</span> · {i18n.attendance.period}{' '}
                                <span dir="ltr">{row.period_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.attendance.section}{' '}
                                <span dir="ltr">{row.section_id}</span> · {i18n.attendance.teacher}{' '}
                                <span dir="ltr">{row.teacher_id}</span>
                            </div>
                        </div>
                    )}
                />
                {pagination.total_pages > 1 ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={pagination.page <= 1}
                            onClick={() => goPage(pagination.page - 1)}
                        >
                            {i18n.common.previous}
                        </button>
                        <span className="text-sm">
                            {i18n.common.page}{' '}
                            <span dir="ltr">
                                {pagination.page} {i18n.common.of} {pagination.total_pages}
                            </span>
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={pagination.page >= pagination.total_pages}
                            onClick={() => goPage(pagination.page + 1)}
                        >
                            {i18n.common.next}
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
