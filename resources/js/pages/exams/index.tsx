import { Head } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type ExamRow = {
    id: number;
    school_id: number;
    academic_year_id: number;
    term_id: number;
    exam_type_id: number;
    name: string;
    start_date: string;
    end_date: string;
    status: number;
};

type PageProps = {
    exams: {
        data: ExamRow[];
    };
    filters: {
        academic_year_id: number | null;
        status: number | null;
    };
};

export default function ExamsIndex({ exams, filters }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.exams.title, href: '/exams' }];

    const columns: DataTableColumn<ExamRow>[] = [
        {
            id: 'name',
            header: i18n.exams.name,
            cell: (row) => row.name,
        },
        {
            id: 'term_id',
            header: i18n.exams.term,
            cell: (row) => <span dir="ltr">{row.term_id}</span>,
            hideOnMobile: true,
        },
        {
            id: 'dates',
            header: i18n.exams.dates,
            cell: (row) => (
                <span dir="ltr">
                    {row.start_date} – {row.end_date}
                </span>
            ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <StatusChip kind="exam" status={row.status} />,
        },
        {
            id: 'actions',
            header: i18n.common.open,
            cell: (row) => (
                <a href={`/exams/${row.id}`} className="underline">
                    {i18n.common.view}
                </a>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.exams.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.exams.title}
                    description={i18n.exams.description}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <OpsYearFilter action="/exams" academicYearId={filters.academic_year_id} />
                <DataTable
                    columns={columns}
                    rows={exams.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.exams.emptyTitle}
                    emptyDescription={
                        filters.academic_year_id ? i18n.exams.emptyDesc : i18n.exams.selectYearContext
                    }
                    caption={i18n.exams.tableCaption}
                    mobileCard={(row) => (
                        <a
                            href={`/exams/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">{row.name}</div>
                            <div className="text-sm opacity-80">
                                <span dir="ltr">
                                    {row.start_date} – {row.end_date}
                                </span>
                            </div>
                            <div className="text-sm opacity-80">
                                <StatusChip kind="exam" status={row.status} />
                            </div>
                        </a>
                    )}
                />
            </div>
        </AppLayout>
    );
}
