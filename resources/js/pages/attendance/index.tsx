import { Head, Link, router } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
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

export default function AttendanceIndex({ sessions, filters }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.attendance.title, href: '/attendance' }];

    const columns: DataTableColumn<SessionRow>[] = [
        {
            id: 'session_date',
            header: i18n.attendance.date,
            cell: (row) => <span dir="ltr">{row.session_date}</span>,
        },
        {
            id: 'section_id',
            header: i18n.attendance.section,
            cell: (row) => <span dir="ltr">{row.section_id}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.attendance.subject,
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
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <span dir="ltr">{row.status}</span>,
        },
        {
            id: 'actions',
            header: i18n.common.open,
            cell: (row) => (
                <a href={`/attendance/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

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
            <Head title={i18n.attendance.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.attendance.title}
                    description={i18n.attendance.description}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <p>
                    <Link href="/attendance/create" className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
                        {i18n.attendance.createSession}
                    </Link>
                </p>
                <DataTable
                    columns={columns}
                    rows={sessions.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.attendance.emptyTitle}
                    emptyDescription={i18n.attendance.emptyDesc}
                    caption={i18n.attendance.tableCaption}
                    mobileCard={(row) => (
                        <a
                            href={`/attendance/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3"
                        >
                            <div className="font-semibold">
                                <span dir="ltr">{row.session_date}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.attendance.section}{' '}
                                <span dir="ltr">{row.section_id}</span> · {i18n.attendance.subject}{' '}
                                <span dir="ltr">{row.subject_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.common.status}{' '}
                                <span dir="ltr">{row.status}</span>
                            </div>
                        </a>
                    )}
                />
                {totalPages > 1 ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                            disabled={currentPage <= 1}
                            onClick={() => goPage(currentPage - 1)}
                        >
                            {i18n.common.previous}
                        </button>
                        <span className="text-sm">
                            {i18n.common.page}{' '}
                            <span dir="ltr">
                                {currentPage} {i18n.common.of} {totalPages}
                            </span>
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                            disabled={currentPage >= totalPages}
                            onClick={() => goPage(currentPage + 1)}
                        >
                            {i18n.common.next}
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
