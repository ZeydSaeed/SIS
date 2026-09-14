import { Head } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Exams', href: '/exams' }];

const columns: DataTableColumn<ExamRow>[] = [
    {
        id: 'name',
        header: 'Name',
        cell: (row) => row.name,
    },
    {
        id: 'term_id',
        header: 'Term',
        cell: (row) => row.term_id,
        hideOnMobile: true,
    },
    {
        id: 'dates',
        header: 'Dates',
        cell: (row) => `${row.start_date} – ${row.end_date}`,
    },
    {
        id: 'status',
        header: 'Status',
        cell: (row) => row.status,
    },
    {
        id: 'actions',
        header: 'Open',
        cell: (row) => (
            <a href={`/exams/${row.id}`} className="underline">
                View
            </a>
        ),
    },
];

export default function ExamsIndex({ exams, filters }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Exams" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Exams"
                    description="Exam definitions for the selected academic year."
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <DataTable
                    columns={columns}
                    rows={exams.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No exams"
                    emptyDescription={
                        filters.academic_year_id
                            ? 'No exams for this academic year yet.'
                            : 'Select an academic year context to load exams.'
                    }
                    caption="School exams"
                    mobileCard={(row) => (
                        <a
                            href={`/exams/${row.id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">{row.name}</div>
                            <div className="text-sm opacity-80">
                                {row.start_date} – {row.end_date}
                            </div>
                            <div className="text-sm opacity-80">Status {row.status}</div>
                        </a>
                    )}
                />
            </div>
        </AppLayout>
    );
}
