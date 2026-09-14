import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Enrollments', href: '/enrollments' }];

const columns: DataTableColumn<EnrollmentRow>[] = [
    {
        id: 'enrollment_number',
        header: 'Enrollment #',
        cell: (row) => row.enrollment_number,
    },
    {
        id: 'student_id',
        header: 'Student',
        cell: (row) => row.student_id,
    },
    {
        id: 'class_section',
        header: 'Class / Section',
        cell: (row) => `${row.class_id} / ${row.section_id}`,
        hideOnMobile: true,
    },
    {
        id: 'status',
        header: 'Status',
        cell: (row) => row.status,
    },
    {
        id: 'effective_from',
        header: 'Effective from',
        cell: (row) => row.effective_from,
        hideOnMobile: true,
    },
    {
        id: 'actions',
        header: 'Open',
        cell: (row) => (
            <a href={`/enrollments/${row.id}`} className="underline">
                View
            </a>
        ),
    },
];

export default function EnrollmentsIndex({ enrollments, filters }: PageProps) {
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
            <Head title="Enrollments" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Enrollments"
                    description="Active student placements for the selected academic year."
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <p>
                    <Link href="/enrollments/create" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Enroll student
                    </Link>
                </p>
                <DataTable
                    columns={columns}
                    rows={enrollments.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No enrollments"
                    emptyDescription="No enrollment rows for this school year yet."
                    caption="School enrollments"
                    mobileCard={(row) => (
                        <a
                            href={`/enrollments/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">{row.enrollment_number}</div>
                            <div className="text-sm opacity-80">Student {row.student_id}</div>
                            <div className="text-sm opacity-80">
                                Class {row.class_id} · Section {row.section_id}
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
