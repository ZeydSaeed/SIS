import { Head, Link } from '@inertiajs/react';
import {
    Ban,
    CirclePlay,
    CircleStop,
    FastForward,
    GraduationCap,
    Pencil,
    Plus,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    ExamFormDialog,
    SessionFormDialog,
    idempotencyKey,
    submit,
    type Option,
} from '@/components/exams/exam-forms';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { type PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { EditRibbon } from '@/components/exams/edit-ribbon';
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
    /** EXAMIS 7.2: display names instead of raw ids. */
    names: {
        academic_years?: Record<number, string>;
        terms?: Record<number, string>;
        subjects?: Record<number, string>;
    };
    admin: {
        can_create_session: boolean;
        can_update: boolean;
        can_cancel: boolean;
        can_update_session: boolean;
        can_open: boolean;
        can_close: boolean;
        subjects: Option[];
        terms: Option[];
        exam_types: Option[];
    };
};

/** exams.exams.status / exams.exam_sessions.status (ExamStatus, ExamSessionStatus). */
const EXAM = {
    draft: 1,
    scheduled: 2,
    inProgress: 3,
    completed: 4,
    cancelled: 5,
} as const;
const SESSION = {
    scheduled: 1,
    inProgress: 2,
    completed: 3,
    cancelled: 4,
} as const;

type Confirm =
    | { kind: 'cancelExam' }
    | { kind: 'advance'; to: number }
    | { kind: 'closeSession' | 'cancelSession'; session: SessionRow };

export default function ExamShow({ exam, sessions, names, admin }: PageProps) {
    const i18n = t();
    const a = i18n.exams.admin;
    const [editingExam, setEditingExam] = useState(false);
    const [newSession, setNewSession] = useState(false);
    const [editingSession, setEditingSession] = useState<SessionRow | null>(
        null,
    );
    const [confirm, setConfirm] = useState<Confirm | null>(null);
    const [busy, setBusy] = useState(false);

    const base = `/exams/${exam.id}`;
    const terminal =
        exam.status === EXAM.completed || exam.status === EXAM.cancelled;
    const editable =
        exam.status === EXAM.draft || exam.status === EXAM.scheduled;
    const nextStatus =
        exam.status === EXAM.draft
            ? EXAM.scheduled
            : exam.status === EXAM.scheduled
              ? EXAM.inProgress
              : exam.status === EXAM.inProgress
                ? EXAM.completed
                : null;
    const subjectName = (id: number) => names.subjects?.[id] ?? '—';

    /** One action without a form (open a session, or a confirmed action); rule refusals come back as flash. */
    const act = (url: string, data: Record<string, number> = {}) => {
        setBusy(true);
        submit(url, data, idempotencyKey('exam-action'), {
            onErrors: () => undefined,
            onFinish: () => {
                setBusy(false);
                setConfirm(null);
            },
        });
    };

    // The «تحرير» ribbon acts on the selected session row (the first one by default).
    const [selectedId, setSelectedId] = useState<number | null>(
        sessions.data[0]?.id ?? null,
    );
    useEffect(() => {
        if (!sessions.data.some((row) => row.id === selectedId)) {
            setSelectedId(sessions.data[0]?.id ?? null);
        }
    }, [sessions.data, selectedId]);
    const selected = sessions.data.find((row) => row.id === selectedId) ?? null;

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [];
        const examCommands: PageRibbonGroup['commands'] = [];
        if (admin.can_update) {
            examCommands.push({
                id: 'exam-edit',
                label: a.editExam,
                icon: Pencil,
                tone: 'edit',
                disabled: !editable || busy,
                onSelect: () => setEditingExam(true),
            });
            examCommands.push({
                id: 'exam-advance',
                label: a.advance,
                title:
                    nextStatus !== null
                        ? a.advanceTo.replace(
                              ':status',
                              i18n.exams.statuses[nextStatus] ?? '',
                          )
                        : undefined,
                icon: FastForward,
                disabled: nextStatus === null || busy,
                onSelect: () =>
                    nextStatus !== null &&
                    setConfirm({ kind: 'advance', to: nextStatus }),
            });
        }
        if (admin.can_cancel) {
            examCommands.push({
                id: 'exam-cancel',
                label: a.cancelExam,
                icon: XCircle,
                tone: 'delete',
                disabled: terminal || busy,
                onSelect: () => setConfirm({ kind: 'cancelExam' }),
            });
        }
        if (examCommands.length > 0) {
            groups.push({ id: 'exam', label: a.exam, commands: examCommands });
        }

        const sessionCommands: PageRibbonGroup['commands'] = [];
        if (admin.can_create_session) {
            sessionCommands.push({
                id: 'session-new',
                label: a.newSession,
                icon: Plus,
                disabled: terminal || admin.subjects.length === 0 || busy,
                onSelect: () => setNewSession(true),
            });
        }
        if (admin.can_update_session) {
            sessionCommands.push({
                id: 'session-edit',
                label: a.editSession,
                icon: Pencil,
                tone: 'edit',
                disabled: selected?.status !== SESSION.scheduled || busy,
                onSelect: () => selected && setEditingSession(selected),
            });
            sessionCommands.push({
                id: 'session-cancel',
                label: a.cancelSession,
                icon: Ban,
                tone: 'delete',
                disabled:
                    !(
                        selected?.status === SESSION.scheduled ||
                        selected?.status === SESSION.inProgress
                    ) || busy,
                onSelect: () =>
                    selected &&
                    setConfirm({ kind: 'cancelSession', session: selected }),
            });
        }
        if (sessionCommands.length > 0) {
            groups.push({
                id: 'sessions',
                label: i18n.exams.sessions,
                commands: sessionCommands,
            });
        }

        const runCommands: PageRibbonGroup['commands'] = [];
        if (admin.can_open) {
            runCommands.push({
                id: 'session-open',
                label: a.open,
                icon: CirclePlay,
                disabled: selected?.status !== SESSION.scheduled || busy,
                onSelect: () =>
                    selected && act(`${base}/sessions/${selected.id}/open`),
            });
        }
        if (admin.can_close) {
            runCommands.push({
                id: 'session-close',
                label: a.close,
                icon: CircleStop,
                disabled: selected?.status !== SESSION.inProgress || busy,
                onSelect: () =>
                    selected &&
                    setConfirm({ kind: 'closeSession', session: selected }),
            });
        }
        if (runCommands.length > 0) {
            groups.push({
                id: 'session-run',
                label: a.run,
                commands: runCommands,
            });
        }

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps -- `act` and the setters are stable for the page's life
    }, [
        admin,
        a,
        i18n.exams,
        editable,
        terminal,
        nextStatus,
        selected,
        busy,
        base,
    ]);

    const sessionColumns: DataTableColumn<SessionRow>[] = [
        {
            id: 'session_date',
            header: i18n.attendance.date,
            cell: (row) => <span dir="ltr">{row.session_date}</span>,
        },
        {
            id: 'subject_id',
            header: i18n.results.subject,
            cell: (row) => subjectName(row.subject_id),
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
            id: 'grades_range',
            header: a.maxGrade,
            cell: (row) => (
                <span dir="ltr">
                    {row.pass_grade} / {row.max_grade}
                </span>
            ),
            hideOnMobile: true,
        },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => (
                <StatusChip kind="examSession" status={row.status} />
            ),
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
        { title: exam.name, href: base },
    ];

    const confirmText = (() => {
        if (confirm === null) return { title: '', description: '', label: '' };
        if (confirm.kind === 'cancelExam')
            return {
                title: a.cancelExamTitle,
                description: a.cancelExamDesc,
                label: a.cancelExam,
            };
        if (confirm.kind === 'advance') {
            const status =
                i18n.exams.statuses[confirm.to] ?? String(confirm.to);
            return {
                title: a.advanceTo.replace(':status', status),
                description: a.advanceDesc,
                label: a.advance,
            };
        }
        if (confirm.kind === 'closeSession')
            return {
                title: a.closeConfirmTitle,
                description: a.closeConfirmDesc,
                label: a.close,
            };
        return {
            title: a.cancelSessionTitle,
            description: a.cancelSessionDesc,
            label: a.cancelSession,
        };
    })();

    const runConfirm = () => {
        if (confirm === null) return;
        if (confirm.kind === 'cancelExam') act(`${base}/cancel`);
        else if (confirm.kind === 'advance')
            act(`${base}/update`, { target_status: confirm.to });
        else if (confirm.kind === 'closeSession')
            act(`${base}/sessions/${confirm.session.id}/close`);
        else act(`${base}/sessions/${confirm.session.id}/cancel`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <EditRibbon groups={ribbon} />
            <Head title={exam.name} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={exam.name}
                    description={i18n.exams.detailDesc}
                    icon={<GraduationCap className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link
                        href="/exams"
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                        prefetch
                    >
                        {i18n.common.backToList}
                    </Link>
                </div>
                {admin.can_update && editable ? (
                    <p className="text-muted-foreground text-sm">
                        {a.editHint}
                    </p>
                ) : null}
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">
                            {i18n.exams.academicYear}
                        </dt>
                        <dd>
                            {names.academic_years?.[exam.academic_year_id] ??
                                '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.exams.term}</dt>
                        <dd>{names.terms?.[exam.term_id] ?? '—'}</dd>
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
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">
                        {i18n.exams.sessions}
                    </h2>
                </div>
                {admin.can_create_session &&
                !terminal &&
                admin.subjects.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {a.noSubjects}
                    </p>
                ) : null}
                <DataTable
                    columns={sessionColumns}
                    rows={sessions.data}
                    rowKey={(row) => row.id}
                    onRowClick={(row) => setSelectedId(row.id)}
                    isRowSelected={(row) => row.id === selectedId}
                    getRowAriaLabel={(row) =>
                        `${subjectName(row.subject_id)} ${row.session_date} ${row.start_time}`
                    }
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
                                {subjectName(row.subject_id)}
                            </div>
                            <div className="mt-1">
                                <StatusChip
                                    kind="examSession"
                                    status={row.status}
                                />
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

            {admin.can_update ? (
                <ExamFormDialog
                    open={editingExam}
                    mode="edit"
                    url={`${base}/update`}
                    academicYearId={exam.academic_year_id}
                    initial={{
                        name: exam.name,
                        term_id: exam.term_id,
                        exam_type_id: exam.exam_type_id,
                        start_date: exam.start_date,
                        end_date: exam.end_date,
                    }}
                    terms={admin.terms}
                    examTypes={admin.exam_types}
                    allowTypeAndTerm={exam.status === EXAM.draft}
                    onClose={() => setEditingExam(false)}
                />
            ) : null}
            {admin.can_create_session ? (
                <SessionFormDialog
                    open={newSession}
                    mode="create"
                    url={`${base}/sessions`}
                    initial={{
                        subject_id: null,
                        session_date: '',
                        start_time: '09:00',
                        end_time: '11:00',
                        max_grade: '100',
                        pass_grade: '50',
                    }}
                    subjects={admin.subjects}
                    onClose={() => setNewSession(false)}
                />
            ) : null}
            {admin.can_update_session ? (
                <SessionFormDialog
                    open={editingSession !== null}
                    mode="edit"
                    url={
                        editingSession
                            ? `${base}/sessions/${editingSession.id}/update`
                            : base
                    }
                    initial={{
                        subject_id: editingSession?.subject_id ?? null,
                        session_date: editingSession?.session_date ?? '',
                        start_time: editingSession?.start_time ?? '',
                        end_time: editingSession?.end_time ?? '',
                        max_grade: String(editingSession?.max_grade ?? ''),
                        pass_grade: String(editingSession?.pass_grade ?? ''),
                    }}
                    subjects={admin.subjects}
                    onClose={() => setEditingSession(null)}
                />
            ) : null}
            <ConfirmDialog
                open={confirm !== null}
                title={confirmText.title}
                description={confirmText.description}
                confirmLabel={confirmText.label}
                confirmPending={busy}
                tone={
                    confirm?.kind === 'cancelExam' ||
                    confirm?.kind === 'cancelSession'
                        ? 'danger'
                        : 'default'
                }
                onConfirm={runConfirm}
                onOpenChange={(open) => {
                    if (!open && !busy) setConfirm(null);
                }}
            />
        </AppLayout>
    );
}
