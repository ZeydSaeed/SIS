import { Head, router } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type SessionRow = {
    id: number;
    school_id: number;
    section_id: number;
    subject_id: number;
    academic_year_id: number;
    session_date: string;
    period_id: number | null;
    teacher_id: number;
    status: number;
};

type PageProps = {
    sessions: {
        data: SessionRow[];
        meta: {
            page?: number;
            per_page?: number;
            total?: number;
            last_page?: number;
            [key: string]: number | undefined;
        };
    };
    filters: {
        academic_year_id: number | null;
        page: number;
        per_page: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attendance', href: '/attendance' }];

const columns: DataTableColumn<SessionRow>[] = [
    {
        id: 'session_date',
        header: 'Date',
        cell: (row) => row.session_date,
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
        id: 'status',
        header: 'Status',
        cell: (row) => row.status,
    },
];

export default function AttendanceIndex({ sessions, filters }: PageProps) {
    const goPage = (page: number) => {
        router.get(
            '/attendance',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                page,
                per_page: filters.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const totalPages = sessions.meta.last_page ?? 1;
    const currentPage = sessions.meta.page ?? filters.page;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Attendance"
                    description="Session list for daily marking and close-out."
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <DataTable
                    columns={columns}
                    rows={sessions.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No attendance sessions"
                    emptyDescription="No sessions for this academic year yet."
                    caption="Attendance sessions"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">{row.session_date}</div>
                            <div className="text-sm opacity-80">
                                Section {row.section_id} · Subject {row.subject_id}
                            </div>
                            <div className="text-sm opacity-80">Status {row.status}</div>
                        </div>
                    )}
                />
                {totalPages > 1 ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={currentPage <= 1}
                            onClick={() => goPage(currentPage - 1)}
                        >
                            Previous
                        </button>
                        <span className="text-sm">
                            Page {currentPage} of {totalPages}
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={currentPage >= totalPages}
                            onClick={() => goPage(currentPage + 1)}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
