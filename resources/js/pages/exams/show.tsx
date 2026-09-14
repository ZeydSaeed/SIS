import { Head, Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type Exam = {
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

type SessionRow = {
    id: number;
    exam_id: number;
    school_id: number;
    subject_id: number;
    session_date: string;
    start_time: string;
    end_time: string;
    room_id: number | null;
    max_grade: number;
    pass_grade: number;
    status: number;
};

type PageProps = {
    exam: Exam;
    sessions: {
        data: SessionRow[];
    };
    filters: {
        session_status: number | null;
    };
};

export default function ExamShow({ exam, sessions }: PageProps) {
    const sessionColumns: DataTableColumn<SessionRow>[] = [
        {
            id: 'session_date',
            header: 'Date',
            cell: (row) => row.session_date,
        },
        {
            id: 'subject_id',
            header: 'Subject',
            cell: (row) => row.subject_id,
        },
        {
            id: 'time',
            header: 'Time',
            cell: (row) => `${row.start_time} – ${row.end_time}`,
            hideOnMobile: true,
        },
        {
            id: 'status',
            header: 'Status',
            cell: (row) => row.status,
        },
        {
            id: 'grades',
            header: 'Grades',
            cell: (row) => (
                <a
                    href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                    className="underline"
                >
                    List
                </a>
            ),
        },
    ];
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Exams', href: '/exams' },
        { title: exam.name, href: `/exams/${exam.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={exam.name} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={exam.name}
                    description="Exam detail and scheduled sessions."
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/exams" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Back to list
                    </Link>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">Academic year</dt>
                        <dd>{exam.academic_year_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Term</dt>
                        <dd>{exam.term_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Dates</dt>
                        <dd>
                            {exam.start_date} – {exam.end_date}
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Status</dt>
                        <dd>{exam.status}</dd>
                    </div>
                </dl>
                <h2 className="text-lg font-semibold">Sessions</h2>
                <DataTable
                    columns={sessionColumns}
                    rows={sessions.data}
                    rowKey={(row) => row.id}
                    emptyTitle="No sessions"
                    emptyDescription="No exam sessions scheduled for this exam yet."
                    caption="Exam sessions"
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">{row.session_date}</div>
                            <div className="text-sm opacity-80">Subject {row.subject_id}</div>
                            <a
                                href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                                className="sis-ops-hub__link mt-2 inline-block text-sm"
                            >
                                View grades
                            </a>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
