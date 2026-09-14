import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { PenLine } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Grades', href: '/grades' }];

const columns: DataTableColumn<GradeRow>[] = [
    {
        id: 'student_id',
        header: 'Student',
        cell: (row) => row.student_id,
    },
    {
        id: 'score',
        header: 'Score',
        cell: (row) => (row.is_absent ? 'Absent' : (row.score ?? '—')),
    },
    {
        id: 'status',
        header: 'Status',
        cell: (row) => row.status,
        hideOnMobile: true,
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: (row) => (
            <a
                href={`/grades/actions?grade_id=${row.id}&academic_year_id=${row.academic_year_id}`}
                className="underline"
            >
                Manage
            </a>
        ),
    },
];

export default function GradesIndex({ grades, filters }: PageProps) {
    const [sessionId, setSessionId] = useState(filters.session_id?.toString() ?? '');

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
            <Head title="Grades" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Grades"
                    description="Read grades for an exam session."
                    icon={<PenLine className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/grades/enter" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Enter grade
                    </Link>
                </div>
                <form
                    onSubmit={onFilter}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                    aria-label="Filter grades by session"
                >
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                        <span>Exam session ID</span>
                        <input
                            type="number"
                            min={1}
                            value={sessionId}
                            onChange={(e) => setSessionId(e.target.value)}
                            className="sis-ops-hub__link min-h-11 px-3 py-2"
                        />
                    </label>
                    <button type="submit" className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm">
                        Load grades
                    </button>
                </form>
                <DataTable
                    columns={columns}
                    rows={grades.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No grades"
                    emptyDescription="Enter a session ID and load grades for the academic year."
                    caption="Session grades"
                    mobileCard={(row) => (
                        <a
                            href={`/grades/actions?grade_id=${row.id}&academic_year_id=${row.academic_year_id}`}
                            className="block rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-blue)_20%,white)] p-3"
                        >
                            <div className="font-semibold">Student {row.student_id}</div>
                            <div className="text-sm opacity-80">
                                {row.is_absent ? 'Absent' : `Score ${row.score ?? '—'}`}
                            </div>
                        </a>
                    )}
                />
            </div>
        </AppLayout>
    );
}
