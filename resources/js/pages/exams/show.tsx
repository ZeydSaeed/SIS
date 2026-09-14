import { Head, Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
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
    const i18n = t();

    const sessionColumns: DataTableColumn<SessionRow>[] = [
        {
            id: 'session_date',
            header: i18n.attendance.date,
            cell: (row) => <span dir="ltr">{row.session_date}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) => <span dir="ltr">{row.subject_id}</span>,
        },
        {
            id: 'time',
            header: i18n.common.time,
            cell: (row) => (
                <span dir="ltr">
                    {row.start_time} – {row.end_time}
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
            id: 'grades',
            header: i18n.modules.grades,
            cell: (row) => (
                <a
                    href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                    className="underline"
                >
                    {i18n.exams.listGrades}
                </a>
            ),
        },
    ];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: exam.name, href: `/exams/${exam.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={exam.name} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={exam.name}
                    description={i18n.exams.detailDesc}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/exams" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        {i18n.common.backToList}
                    </Link>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">{i18n.exams.academicYear}</dt>
                        <dd>
                            <span dir="ltr">{exam.academic_year_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.exams.term}</dt>
                        <dd>
                            <span dir="ltr">{exam.term_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.exams.dates}</dt>
                        <dd>
                            <span dir="ltr">
                                {exam.start_date} – {exam.end_date}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.common.status}</dt>
                        <dd>
                            <span dir="ltr">{exam.status}</span>
                        </dd>
                    </div>
                </dl>
                <h2 className="text-lg font-semibold">{i18n.exams.sessions}</h2>
                <DataTable
                    columns={sessionColumns}
                    rows={sessions.data}
                    rowKey={(row) => row.id}
                    emptyTitle={i18n.exams.noSessionsTitle}
                    emptyDescription={i18n.exams.noSessionsDesc}
                    caption={i18n.exams.sessionsCaption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">
                                <span dir="ltr">{row.session_date}</span>
                            </div>
                            <div className="text-sm opacity-80">
                                {i18n.results.subject}{' '}
                                <span dir="ltr">{row.subject_id}</span>
                            </div>
                            <a
                                href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                                className="sis-ops-hub__link mt-2 inline-block text-sm"
                            >
                                {i18n.exams.viewGrades}
                            </a>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
