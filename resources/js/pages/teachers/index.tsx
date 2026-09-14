import { Head, router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
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

export default function TeachersIndex({ teachers, filters }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.teachers.title, href: '/teachers' }];

    const columns: DataTableColumn<TeacherRow>[] = [
        {
            id: 'employee_code',
            header: i18n.teachers.code,
            cell: (row) => <span dir="ltr">{row.employee_code}</span>,
        },
        {
            id: 'full_name',
            header: i18n.teachers.name,
            cell: (row) => row.full_name,
        },
        {
            id: 'specialization_field',
            header: i18n.teachers.specialization,
            cell: (row) => row.specialization_field ?? '—',
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
                <a href={`/teachers/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

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
            <Head title={i18n.teachers.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.teachers.title}
                    description={i18n.teachers.description}
                    icon={<Users className="size-6" aria-hidden />}
                />
                <OpsYearFilter
                    action="/teachers"
                    academicYearId={filters.academic_year_id}
                    extraParams={{ per_page: filters.per_page }}
                />
                <DataTable
                    columns={columns}
                    rows={teachers.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.teachers.emptyTitle}
                    emptyDescription={i18n.teachers.emptyDesc}
                    caption={i18n.teachers.tableCaption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_18%,white)] p-3">
                            <div className="font-semibold">{row.full_name}</div>
                            <div className="text-sm opacity-80">
                                <span dir="ltr">{row.employee_code}</span>
                            </div>
                        </div>
                    )}
                />
                {teachers.meta.last_page > 1 ? (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                            disabled={teachers.meta.page <= 1}
                            onClick={() => goPage(teachers.meta.page - 1)}
                        >
                            {i18n.common.previous}
                        </button>
                        <span className="text-sm">
                            {i18n.common.page}{' '}
                            <span dir="ltr">
                                {teachers.meta.page} {i18n.common.of} {teachers.meta.last_page}
                            </span>
                        </span>
                        <button
                            type="button"
                            className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                            disabled={teachers.meta.page >= teachers.meta.last_page}
                            onClick={() => goPage(teachers.meta.page + 1)}
                        >
                            {i18n.common.next}
                        </button>
                    </div>
                ) : null}
            </div>
        </AppLayout>
    );
}
