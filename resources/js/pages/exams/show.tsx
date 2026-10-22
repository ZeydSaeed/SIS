import { Head, Link, router } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
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

type SeatingClass = {
    id: number;
    name: string;
    sections: { id: number; name: string }[];
};

type Seating = {
    can_seat: boolean;
    classes: SeatingClass[];
    seat_counts: Record<number, number>;
    result: { session_id: number; counts: Record<string, number> } | null;
};

type PageProps = {
    exam: Exam;
    sessions: {
        data: SessionRow[];
    };
    seating: Seating;
    names: {
        terms: Record<number, string>;
        academic_years: Record<number, string>;
        subjects: Record<number, string>;
    };
    filters: {
        session_status: number | null;
    };
};

/** Session status 4 = cancelled: no seating. */
const SESSION_CANCELLED = 4;

function newKey(): string {
    return `exam-seat-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export default function ExamShow({
    exam,
    sessions,
    seating,
    names,
}: PageProps) {
    const i18n = t();
    const s = i18n.exams.seat;
    const [seatingFor, setSeatingFor] = useState<SessionRow | null>(null);
    const [classId, setClassId] = useState('');
    const [sectionId, setSectionId] = useState('');
    const [submitKey, setSubmitKey] = useState('');
    const [processing, setProcessing] = useState(false);
    const sections =
        seating.classes.find((c) => String(c.id) === classId)?.sections ?? [];

    const openSeating = (row: SessionRow) => {
        setSeatingFor(row);
        setClassId('');
        setSectionId('');
        setSubmitKey(newKey());
    };

    const submitSeating = () => {
        if (!seatingFor || classId === '' || processing) {
            return;
        }
        setProcessing(true);
        router.post(
            `/exams/${exam.id}/sessions/${seatingFor.id}/seat`,
            {
                class_id: Number(classId),
                section_id: sectionId === '' ? null : Number(sectionId),
            },
            {
                headers: { 'X-Idempotency-Key': submitKey },
                preserveScroll: true,
                onSuccess: () => setSeatingFor(null),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const seatingSummary = (row: SessionRow) => {
        if (seating.result?.session_id !== row.id) {
            return null;
        }
        const counts = seating.result.counts;

        return Object.entries(counts).reduce<string>(
            (text, [key, value]) => text.replace(`:${key}`, String(value)),
            s.result,
        );
    };

    const sheetHref = (row: SessionRow) =>
        `/grades/sheet?academic_year_id=${exam.academic_year_id}&session_id=${row.id}`;

    const sessionColumns: DataTableColumn<SessionRow>[] = [
        {
            id: 'session_date',
            header: i18n.attendance.date,
            cell: (row) => <span dir="ltr">{row.session_date}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) =>
                names.subjects[row.subject_id] ?? (
                    <span dir="ltr">{row.subject_id}</span>
                ),
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
            cell: (row) => <StatusChip kind="exam" status={row.status} />,
        },
        {
            id: 'seats',
            header: s.column,
            cell: (row) => (
                <span dir="ltr">{seating.seat_counts[row.id] ?? 0}</span>
            ),
        },
        {
            id: 'grades',
            header: i18n.modules.grades,
            cell: (row) => (
                <span className="flex flex-wrap gap-3">
                    <a href={sheetHref(row)} className="underline">
                        {i18n.exams.enterGrades}
                    </a>
                    <a
                        href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                        className="underline"
                    >
                        {i18n.exams.listGrades}
                    </a>
                    {seating.can_seat && row.status !== SESSION_CANCELLED ? (
                        <button
                            type="button"
                            className="underline"
                            onClick={() => openSeating(row)}
                        >
                            {s.action}
                        </button>
                    ) : null}
                </span>
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
            <div
                className="sis-ops-hub flex flex-col gap-4 p-4"
                dir="rtl"
                lang="ar"
            >
                <PageHeader
                    title={exam.name}
                    description={i18n.exams.detailDesc}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link
                        href="/exams"
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                        dir="rtl"
                        lang="ar"
                        prefetch
                    >
                        {i18n.common.backToList}
                    </Link>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">
                            {i18n.exams.academicYear}
                        </dt>
                        <dd>
                            {names.academic_years[exam.academic_year_id] ?? (
                                <span dir="ltr">{exam.academic_year_id}</span>
                            )}
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.exams.term}</dt>
                        <dd>
                            {names.terms[exam.term_id] ?? (
                                <span dir="ltr">{exam.term_id}</span>
                            )}
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
                            <StatusChip kind="exam" status={exam.status} />
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
                                {names.subjects[row.subject_id] ?? (
                                    <span dir="ltr">{row.subject_id}</span>
                                )}
                            </div>
                            <div className="mt-1">
                                <StatusChip kind="exam" status={row.status} />
                            </div>
                            <a
                                href={`/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`}
                                className="sis-ops-hub__link mt-2 inline-block text-sm"
                                dir="rtl"
                                lang="ar"
                            >
                                {i18n.exams.viewGrades}
                            </a>
                            <a
                                href={sheetHref(row)}
                                className="sis-ops-hub__link ms-2 mt-2 inline-block text-sm"
                            >
                                {i18n.exams.enterGrades}
                            </a>
                            {seating.can_seat &&
                            row.status !== SESSION_CANCELLED ? (
                                <button
                                    type="button"
                                    className="sis-ops-hub__link ms-2 mt-2 inline-block text-sm"
                                    onClick={() => openSeating(row)}
                                >
                                    {s.action}
                                </button>
                            ) : null}
                        </div>
                    )}
                />
                {sessions.data.map((row) => {
                    const summary = seatingSummary(row);

                    return summary ? (
                        <p
                            key={row.id}
                            className="text-sm"
                            role="status"
                            data-seating-result={row.id}
                        >
                            <span dir="ltr">{row.session_date}</span> —{' '}
                            {summary}
                        </p>
                    ) : null;
                })}
                {seatingFor ? (
                    <section
                        aria-label={s.title}
                        className="flex max-w-xl flex-col gap-3"
                        data-window-id="exams.session.seat"
                    >
                        <h2 className="text-lg font-semibold">
                            {s.title} —{' '}
                            <span dir="ltr">{seatingFor.session_date}</span>
                        </h2>
                        <p className="text-sm">{s.hint}</p>
                        {seating.classes.length === 0 ? (
                            <p className="text-sm">{s.noClasses}</p>
                        ) : (
                            <>
                                <label className="flex flex-col gap-1 text-sm">
                                    <span>{s.class}</span>
                                    <select
                                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                                        value={classId}
                                        onChange={(e) => {
                                            setClassId(e.target.value);
                                            setSectionId('');
                                        }}
                                    >
                                        <option value="">—</option>
                                        {seating.classes.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="flex flex-col gap-1 text-sm">
                                    <span>{s.section}</span>
                                    <select
                                        className="sis-ops-hub__link min-h-11 px-3 py-2"
                                        value={sectionId}
                                        disabled={classId === ''}
                                        onChange={(e) =>
                                            setSectionId(e.target.value)
                                        }
                                    >
                                        <option value="">
                                            {s.allSections}
                                        </option>
                                        {sections.map((section) => (
                                            <option
                                                key={section.id}
                                                value={section.id}
                                            >
                                                {section.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </>
                        )}
                        <div className="flex gap-2">
                            <button
                                type="button"
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                                disabled={classId === '' || processing}
                                onClick={submitSeating}
                            >
                                {processing ? s.submitting : s.submit}
                            </button>
                            <button
                                type="button"
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                                onClick={() => setSeatingFor(null)}
                            >
                                {s.cancel}
                            </button>
                        </div>
                    </section>
                ) : null}
            </div>
        </AppLayout>
    );
}
