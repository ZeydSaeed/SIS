import { Head, router } from '@inertiajs/react';
import { CalendarRange } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Timetable', href: '/timetable' }];

const columns: DataTableColumn<ScheduleRow>[] = [
    {
        id: 'day_of_week',
        header: 'Day',
        cell: (row) => row.day_of_week,
    },
    {
        id: 'period_id',
        header: 'Period',
        cell: (row) => row.period_id,
    },
    {
        id: 'section_id',
        header: 'Section',
        cell: (row) => row.section_id,
    },
    {
        id: 'subject_id',
        header: 'Subject',
        cell: (row) => row.subject_id,
        hideOnMobile: true,
    },
    {
        id: 'teacher_id',
        header: 'Teacher',
        cell: (row) => row.teacher_id,
        hideOnMobile: true,
    },
    {
        id: 'room_id',
        header: 'Room',
        cell: (row) => row.room_id ?? '—',
        hideOnMobile: true,
    },
];

export default function TimetableIndex({ schedules, filters }: PageProps) {
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
            <Head title="Timetable" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Timetable"
                    description="Active section schedules with teacher and room slots."
                    icon={<CalendarRange className="size-6" aria-hidden />}
                />
                <DataTable
                    columns={columns}
                    rows={schedules.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No schedules"
                    emptyDescription="No timetable rows for this academic year yet."
                    caption="Schedules"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_35%,white)] p-3">
                            <div className="font-semibold">
                                Day {row.day_of_week} · Period {row.period_id}
                            </div>
                            <div className="text-sm opacity-80">
                                Section {row.section_id} · Teacher {row.teacher_id}
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
                            Previous
                        </button>
                        <span className="text-sm">
                            Page {pagination.page} of {pagination.total_pages}
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={pagination.page >= pagination.total_pages}
                            onClick={() => goPage(pagination.page + 1)}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
