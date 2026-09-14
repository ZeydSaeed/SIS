import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsYearFilter } from '@/components/sis/ops-year-filter';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type GradeRow = {
    id: number;
    academic_year_id: number;
    school_id: number;
    exam_enrollment_id: number;
    exam_session_id: number;
    enrollment_id: number;
    student_id: number;
    subject_id: number;
    score: string | null;
    max_score: string;
    is_absent: boolean;
    status: number;
    is_current: boolean;
};

type PageProps = {
    grades: {
        data: GradeRow[];
    };
    filters: {
        session_id: number | null;
        academic_year_id: number | null;
    };
};

export default function GradesIndex({ grades, filters }: PageProps) {
    const i18n = t();
    const [sessionId, setSessionId] = useState(filters.session_id?.toString() ?? '');

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.grades.title, href: '/grades' }];

    const columns: DataTableColumn<GradeRow>[] = [
        {
            id: 'student_id',
            header: i18n.grades.student,
            cell: (row) => <span dir="ltr">{row.student_id}</span>,
        },
        {
            id: 'score',
            header: i18n.grades.score,
            cell: (row) => (
                <span dir="ltr">{row.is_absent ? i18n.grades.absent : (row.score ?? '—')}</span>
            ),
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => <StatusChip kind="generic" status={row.status} />,
            hideOnMobile: true,
        },
        {
            id: 'actions',
            header: i18n.common.actions,
            cell: (row) => (
                <a
                    href={`/grades/actions?grade_id=${row.id}&academic_year_id=${row.academic_year_id}`}
                    className="underline"
                >
                    {i18n.grades.manage}
                </a>
            ),
        },
    ];

    const onFilter = (event: FormEvent) => {
        event.preventDefault();
        const parsed = Number.parseInt(sessionId, 10);
        router.get(
            '/grades',
            {
                session_id: Number.isFinite(parsed) && parsed > 0 ? parsed : undefined,
                academic_year_id: filters.academic_year_id ?? undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.grades.title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.grades.title}
                    description={i18n.grades.description}
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/grades/enter" className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
                        {i18n.grades.enterGrade}
                    </Link>
                </div>
                <OpsYearFilter
                    action="/grades"
                    academicYearId={filters.academic_year_id}
                    extraParams={{ session_id: filters.session_id ?? undefined }}
                />
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label={i18n.common.filterGradesBySession}
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>{i18n.grades.sessionId}</span>
                        <input
                            type="number"
                            min={1}
                            value={sessionId}
                            onChange={(e) => setSessionId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                            dir="ltr"
                            inputMode="numeric"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm" dir="rtl" lang="ar">
                        {i18n.grades.loadGrades}
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={grades.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.grades.emptyTitle}
                    emptyDescription={i18n.grades.emptyDesc}
                    caption={i18n.grades.sessionGradesCaption}
                    mobileCard={(row) => (
                        <a
                            href={`/grades/actions?grade_id=${row.id}&academic_year_id=${row.academic_year_id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">
                                {i18n.grades.student}{' '}
                                <span dir="ltr">{row.student_id}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {row.is_absent ? (
                                    i18n.grades.absent
                                ) : (
                                    <>
                                        {i18n.grades.score}{' '}
                                        <span dir="ltr">{row.score ?? '—'}</span>
                                    </>
                                )}
                            </div>
                            <div className="mt-1">
                                <StatusChip kind="generic" status={row.status} />
                            </div>
                        </a>
                    )}
                />
            </div>
        </AppLayout>
    );
}
