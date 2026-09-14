import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type TeacherRow = {
    id: number;
    employee_code: string;
    full_name: string;
    specialization_field: string | null;
    status: number;
    is_primary: boolean;
    academic_year_id: number;
};

type PageProps = {
    teachers: {
        data: TeacherRow[];
        meta: {
            total: number;
            page: number;
            per_page: number;
            last_page: number;
        };
    };
    filters: {
        academic_year_id: number | null;
        page: number;
        per_page: number;
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Teachers', href: '/teachers' }];

const columns: DataTableColumn<TeacherRow>[] = [
    {
        id: 'employee_code',
        header: 'Code',
        cell: (row) => row.employee_code,
    },
    {
        id: 'full_name',
        header: 'Name',
        cell: (row) => row.full_name,
    },
    {
        id: 'specialization_field',
        header: 'Specialization',
        cell: (row) => row.specialization_field ?? '—',
        hideOnMobile: true,
    },
    {
        id: 'status',
        header: 'Status',
        cell: (row) => row.status,
    },
];

export default function TeachersIndex({ teachers, filters }: PageProps) {
    const goPage = (page: number) => {
        router.get(
            '/teachers',
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
            <Head title="Teachers" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Teachers"
                    description="School teachers assigned for the academic year."
                    icon={<Users className="size-6" aria-hidden />}
                />
                <DataTable
                    columns={columns}
                    rows={teachers.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No teachers"
                    emptyDescription="No teachers linked to this school year yet."
                    caption="Teachers"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_18%,white)] p-3">
                            <div className="font-semibold">{row.full_name}</div>
                            <div className="text-sm opacity-80">{row.employee_code}</div>
                        </div>
                    )}
                />
                {teachers.meta.last_page > 1 ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={teachers.meta.page <= 1}
                            onClick={() => goPage(teachers.meta.page - 1)}
                        >
                            Previous
                        </button>
                        <span className="text-sm">
                            Page {teachers.meta.page} of {teachers.meta.last_page}
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            disabled={teachers.meta.page >= teachers.meta.last_page}
                            onClick={() => goPage(teachers.meta.page + 1)}
                        >
                            Next
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
