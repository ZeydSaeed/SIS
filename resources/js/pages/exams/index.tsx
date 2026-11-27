import { Head, router } from '@inertiajs/react';
import { Eye, GraduationCap, Library, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ExamFormDialog, type Option } from '@/components/exams/exam-forms';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { StatusChip } from '@/components/sis/status-chip';
import { EditRibbon } from '@/components/exams/edit-ribbon';
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
    /** EXAMIS 7.2: display names (`terms[id]`) instead of raw ids. */
    names: { terms?: Record<number, string> };
    admin: {
        can_create: boolean;
        terms: Option[];
        exam_types: Option[];
        /** EXAMIS 7.3a: «بنك الأسئلة» is linked only for users who may open it. */
        can_view_bank: boolean;
    };
};

export default function ExamsIndex({ exams, filters, names, admin }: PageProps) {
    const i18n = t();
    const [creating, setCreating] = useState(false);
    // The «تحرير» ribbon: «امتحان جديد» (exam.create) and «عرض» of the selected row.
    const [selectedId, setSelectedId] = useState<number | null>(exams.data[0]?.id ?? null);
    useEffect(() => {
        if (!exams.data.some((row) => row.id === selectedId)) {
            setSelectedId(exams.data[0]?.id ?? null);
        }
    }, [exams.data, selectedId]);
    const ribbon = useMemo((): PageRibbonGroup[] => {
        const commands: PageRibbonGroup['commands'] = [];
        if (admin.can_create) {
            commands.push({
                id: 'exam-new',
                label: i18n.exams.admin.newExam,
                icon: Plus,
                disabled: admin.exam_types.length === 0 || admin.terms.length === 0,
                onSelect: () => setCreating(true),
            });
        }
        commands.push({
            id: 'exam-view',
            label: i18n.common.view,
            icon: Eye,
            disabled: selectedId === null,
            onSelect: () => selectedId !== null && router.visit(`/exams/${selectedId}`),
        });
        if (admin.can_view_bank) {
            commands.push({
                id: 'exam-bank',
                label: i18n.bank.link,
                icon: Library,
                onSelect: () => router.visit('/bank/questions'),
            });
        }

        return [{ id: 'exams', label: i18n.exams.title, commands }];
    }, [admin, i18n, selectedId]);

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
            cell: (row) => names.terms?.[row.term_id] ?? '—',
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
            <EditRibbon groups={ribbon} />
            <Head title={i18n.exams.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.exams.title}
                    description={i18n.exams.description}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <OpsYearFilter action="/exams" academicYearId={filters.academic_year_id} label={i18n.context.year} />
                {admin.can_create && admin.exam_types.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{i18n.exams.admin.noExamTypes}</p>
                ) : null}
                {admin.can_create && admin.terms.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{i18n.exams.admin.noTerms}</p>
                ) : null}
                <DataTable
                    columns={columns}
                    rows={exams.data}
                    rowKey={(row) => row.id}
                    onRowClick={(row) => setSelectedId(row.id)}
                    isRowSelected={(row) => row.id === selectedId}
                    getRowAriaLabel={(row) => row.name}
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
            {admin.can_create ? (
                <ExamFormDialog
                    open={creating}
                    mode="create"
                    url="/exams"
                    academicYearId={filters.academic_year_id}
                    initial={{ name: '', term_id: null, exam_type_id: null, start_date: '', end_date: '' }}
                    terms={admin.terms}
                    examTypes={admin.exam_types}
                    allowTypeAndTerm
                    onClose={() => setCreating(false)}
                />
            ) : null}
        </AppLayout>
    );
}
