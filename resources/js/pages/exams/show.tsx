import { Head, Link, router, useForm } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
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
    admin: {
        can_create_session: boolean;
        can_open: boolean;
        can_close: boolean;
        subjects: { id: number; name: string }[];
    };
    names: {
        terms: Record<number, string>;
        academic_years: Record<number, string>;
        subjects: Record<number, string>;
    };
    filters: {
        session_status: number | null;
    };
};

/** exams.exam_sessions.status: 1 scheduled · 2 in progress · 3 completed · 4 cancelled (no seating). */
const SESSION_SCHEDULED = 1;
const SESSION_IN_PROGRESS = 2;
const SESSION_CANCELLED = 4;
const SELECT_CLASS = 'sis-ops-hub__link min-h-11 px-3 py-2';

function newKey(): string {
    return `exam-seat-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/** «جلسة جديدة» — subjects of the school-year's active curricula; the server validates everything again. */
function CreateSessionForm({
    examId,
    subjects,
    onDone,
}: {
    examId: number;
    subjects: { id: number; name: string }[];
    onDone: () => void;
}) {
    const a = t().exams.admin;
    const [key] = useState(newKey);
    const form = useForm({
        subject_id: '',
        session_date: '',
        start_time: '',
        end_time: '',
        max_grade: '100',
        pass_grade: '50',
    });

    if (subjects.length === 0) {
        return (
            <p className="text-sm" role="status">
                {a.noSubjects}
            </p>
        );
    }

    return (
        <form
            className="grid max-w-xl gap-3"
            data-window-id="exams.session.create"
            onSubmit={(e) => {
                e.preventDefault();
                // <input type="time"> gives HH:MM; the request expects HH:MM:SS.
                form.transform((data) => ({
                    ...data,
                    start_time: `${data.start_time}:00`,
                    end_time: `${data.end_time}:00`,
                }));
                form.post(`/exams/${examId}/sessions`, {
                    headers: { 'X-Idempotency-Key': key },
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <OpsFormField
                label={a.subject}
                name="subject_id"
                error={form.errors.subject_id}
            >
                <select
                    id="subject_id"
                    name="subject_id"
                    required
                    className={SELECT_CLASS}
                    value={form.data.subject_id}
                    onChange={(e) => form.setData('subject_id', e.target.value)}
                >
                    <option value="">—</option>
                    {subjects.map((subject) => (
                        <option key={subject.id} value={subject.id}>
                            {subject.name}
                        </option>
                    ))}
                </select>
            </OpsFormField>
            <OpsFormField
                label={a.sessionDate}
                name="session_date"
                error={form.errors.session_date}
            >
                <OpsTextInput
                    name="session_date"
                    type="date"
                    dir="ltr"
                    required
                    value={form.data.session_date}
                    error={form.errors.session_date}
                    onChange={(e) =>
                        form.setData('session_date', e.target.value)
                    }
                />
            </OpsFormField>
            <OpsFormField
                label={a.startTime}
                name="start_time"
                error={form.errors.start_time}
            >
                <OpsTextInput
                    name="start_time"
                    type="time"
                    dir="ltr"
                    required
                    value={form.data.start_time}
                    error={form.errors.start_time}
                    onChange={(e) => form.setData('start_time', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField
                label={a.endTime}
                name="end_time"
                error={form.errors.end_time}
            >
                <OpsTextInput
                    name="end_time"
                    type="time"
                    dir="ltr"
                    required
                    value={form.data.end_time}
                    error={form.errors.end_time}
                    onChange={(e) => form.setData('end_time', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField
                label={a.maxGrade}
                name="max_grade"
                error={form.errors.max_grade}
            >
                <OpsTextInput
                    name="max_grade"
                    type="number"
                    min={1}
                    dir="ltr"
                    required
                    value={form.data.max_grade}
                    error={form.errors.max_grade}
                    onChange={(e) => form.setData('max_grade', e.target.value)}
                />
            </OpsFormField>
            <OpsFormField
                label={a.passGrade}
                name="pass_grade"
                error={form.errors.pass_grade}
            >
                <OpsTextInput
                    name="pass_grade"
                    type="number"
                    min={0}
                    dir="ltr"
                    required
                    value={form.data.pass_grade}
                    error={form.errors.pass_grade}
                    onChange={(e) => form.setData('pass_grade', e.target.value)}
                />
            </OpsFormField>
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm disabled:opacity-50"
                >
                    {form.processing ? a.saving : a.addSession}
                </button>
                <button
                    type="button"
                    className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                    onClick={onDone}
                >
                    {a.cancel}
                </button>
            </div>
        </form>
    );
}

export default function ExamShow({
    exam,
    sessions,
    seating,
    admin,
    names,
}: PageProps) {
    const i18n = t();
    const s = i18n.exams.seat;
    const [seatingFor, setSeatingFor] = useState<SessionRow | null>(null);
    const [classId, setClassId] = useState('');
    const [sectionId, setSectionId] = useState('');
    const [submitKey, setSubmitKey] = useState('');
    const [processing, setProcessing] = useState(false);
    const [creatingSession, setCreatingSession] = useState(false);
    const [closing, setClosing] = useState<SessionRow | null>(null);
    const [transitioning, setTransitioning] = useState(false);
    const transition = (row: SessionRow, action: 'open' | 'close') => {
        setTransitioning(true);
        router.post(
            `/exams/${exam.id}/sessions/${row.id}/${action}`,
            {},
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onFinish: () => {
                    setTransitioning(false);
                    setClosing(null);
                },
            },
        );
    };
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

    const rosterHref = (row: SessionRow) =>
        `/exams/${exam.id}/sessions/${row.id}`;
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
                    <Link href={rosterHref(row)} className="underline" prefetch>
                        {i18n.exams.roster.open}
                    </Link>
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
                    {admin.can_open && row.status === SESSION_SCHEDULED ? (
                        <button
                            type="button"
                            className="underline"
                            disabled={transitioning}
                            onClick={() => transition(row, 'open')}
                        >
                            {i18n.exams.admin.open}
                        </button>
                    ) : null}
                    {admin.can_close && row.status === SESSION_IN_PROGRESS ? (
                        <button
                            type="button"
                            className="underline"
                            disabled={transitioning}
                            onClick={() => setClosing(row)}
                        >
                            {i18n.exams.admin.close}
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
                {admin.can_create_session ? (
                    creatingSession ? (
                        <CreateSessionForm
                            examId={exam.id}
                            subjects={admin.subjects}
                            onDone={() => setCreatingSession(false)}
                        />
                    ) : (
                        <button
                            type="button"
                            className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                            onClick={() => setCreatingSession(true)}
                        >
                            {i18n.exams.admin.newSession}
                        </button>
                    )
                ) : null}
                <ConfirmDialog
                    open={closing !== null}
                    title={i18n.exams.admin.closeConfirmTitle}
                    description={i18n.exams.admin.closeConfirmDesc}
                    confirmLabel={i18n.exams.admin.close}
                    confirmPending={transitioning}
                    tone="danger"
                    onConfirm={() => {
                        if (closing) {
                            transition(closing, 'close');
                        }
                    }}
                    onOpenChange={(open) => {
                        if (!open) {
                            setClosing(null);
                        }
                    }}
                />
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
                            <Link
                                href={rosterHref(row)}
                                className="sis-ops-hub__link ms-2 mt-2 inline-block text-sm"
                            >
                                {i18n.exams.roster.open}
                            </Link>
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
