import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type EnrollmentRow = {
    id: number;
    student_id: number;
    school_id: number;
    academic_year_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    status: number;
    effective_from: string;
    effective_to: string | null;
};

type PageProps = {
    enrollments: {
        data: EnrollmentRow[];
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

export default function EnrollmentsIndex({ enrollments, filters }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.enrollments.title, href: '/enrollments' }];

    const columns: DataTableColumn<EnrollmentRow>[] = [
        {
            id: 'enrollment_number',
            header: i18n.enrollments.enrollmentNumber,
            cell: (row) => <span dir="ltr">{row.enrollment_number}</span>,
        },
        {
            id: 'student_id',
            header: i18n.enrollments.student,
            cell: (row) => <span dir="ltr">{row.student_id}</span>,
        },
        {
            id: 'class_section',
            header: i18n.enrollments.classSection,
            cell: (row) => (
                <span dir="ltr">
                    {row.class_id} / {row.section_id}
                </span>
            ),
            hideOnMobile: true,
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <span dir="ltr">{row.status}</span>,
        },
        {
            id: 'effective_from',
            header: i18n.enrollments.effectiveFrom,
            cell: (row) => <span dir="ltr">{row.effective_from}</span>,
            hideOnMobile: true,
        },
        {
            id: 'actions',
            header: i18n.common.open,
            cell: (row) => (
                <a href={`/enrollments/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

    const goPage = (page: number) => {
        router.get(
            '/enrollments',
            {
                academic_year_id: filters.academic_year_id ?? undefined,
                page,
                per_page: filters.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const totalPages = enrollments.meta.last_page ?? 1;
    const currentPage = enrollments.meta.page ?? filters.page;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.enrollments.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.enrollments.title}
                    description={i18n.enrollments.description}
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <p>
                    <Link href="/enrollments/create" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        {i18n.enrollments.enrollStudent}
                    </Link>
                </p>
                <DataTable
                    columns={columns}
                    rows={enrollments.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.enrollments.emptyTitle}
                    emptyDescription={i18n.enrollments.emptyDesc}
                    caption={i18n.enrollments.tableCaption}
                    mobileCard={(row) => (
                        <a
                            href={`/enrollments/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">
                                <span dir="ltr">{row.enrollment_number}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.enrollments.student}{' '}
                                <span dir="ltr">{row.student_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                <span dir="ltr">
                                    {row.class_id} · {row.section_id}
                                </span>
                            </div>
                        </a>
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
                            className="sis-ops-hub__link px-3 py-2 text-sm"
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
