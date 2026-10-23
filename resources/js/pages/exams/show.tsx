import { router } from '@inertiajs/react';
import {
    ClipboardList,
    ClipboardPen,
    ListChecks,
    PlayCircle,
    PlusCircle,
    StopCircle,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceMessages,
    useAttendanceSelection,
    WindowTimeField,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { statusChipLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
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
const STATUS_TONE: Record<number, number> = { 1: 0, 2: 1, 3: 2, 4: 4 };

type SessionDraft = {
    subject_id: string;
    session_date: string;
    start_time: string;
    end_time: string;
    max_grade: string;
    pass_grade: string;
};

const EMPTY_SESSION: SessionDraft = {
    subject_id: '',
    session_date: '',
    start_time: '',
    end_time: '',
    max_grade: '100',
    pass_grade: '50',
};

export default function ExamShow(props: PageProps) {
    const i18n = t();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: props.exam.name, href: `/exams/${props.exam.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <ExamBody {...props} />
        </AppLayout>
    );
}

function ExamBody({ exam, sessions, seating, admin, names }: PageProps) {
    const i18n = t();
    const s = i18n.exams.seat;
    const a = i18n.exams.admin;
    const request = useExamisRequest();
    const rows = sessions.data;
    const selection = useAttendanceSelection(
        rows,
        (row) => row.id,
        `exam-${exam.id}-sessions`,
    );
    const target = selection.targets.length === 1 ? selection.targets[0] : null;
    const [draft, setDraft] = useState<SessionDraft | null>(null);
    const [seatingFor, setSeatingFor] = useState<SessionRow | null>(null);
    const [classId, setClassId] = useState('');
    const [sectionId, setSectionId] = useState('');
    const [closing, setClosing] = useState<SessionRow | null>(null);
    const [busy, setBusy] = useState(false);

    const rosterHref = (row: SessionRow) =>
        `/exams/${exam.id}/sessions/${row.id}`;
    const sheetHref = (row: SessionRow) =>
        `/grades/sheet?academic_year_id=${exam.academic_year_id}&session_id=${row.id}`;
    const gradesHref = (row: SessionRow) =>
        `/grades?session_id=${row.id}&academic_year_id=${exam.academic_year_id}`;

    const transition = async (row: SessionRow, action: 'open' | 'close') => {
        setBusy(true);
        await request(
            `/exams/${exam.id}/sessions/${row.id}/${action}`,
            {},
            `exam-session-${action}`,
        );
        setBusy(false);
        setClosing(null);
    };

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const none = target === null;
        const live = target !== null && target.status !== SESSION_CANCELLED;
        const groups: PageRibbonGroup[] = [
            {
                id: 'exam-session-view',
                label: i18n.common.open,
                commands: [
                    {
                        id: 'exam-session-view-roster',
                        label: i18n.exams.roster.open,
                        icon: ClipboardList,
                        iconTone: 'sky',
                        disabled: none,
                        onSelect: () =>
                            target && router.visit(rosterHref(target)),
                    },
                    {
                        id: 'exam-session-enter-grades',
                        label: i18n.exams.enterGrades,
                        icon: ClipboardPen,
                        iconTone: 'growth',
                        disabled: none,
                        onSelect: () =>
                            target && router.visit(sheetHref(target)),
                    },
                    {
                        id: 'exam-session-list-grades',
                        label: i18n.exams.listGrades,
                        icon: ListChecks,
                        iconTone: 'forest',
                        disabled: none,
                        onSelect: () =>
                            target && router.visit(gradesHref(target)),
                    },
                ],
            },
            {
                id: 'exam-session-manage',
                label: i18n.exams.sessions,
                commands: [
                    ...(seating.can_seat
                        ? [
                              {
                                  id: 'exam-session-seat',
                                  label: s.action,
                                  icon: Users,
                                  iconTone: 'education' as const,
                                  disabled: !live,
                                  onSelect: () => {
                                      if (target) {
                                          setSeatingFor(target);
                                          setClassId('');
                                          setSectionId('');
                                      }
                                  },
                              },
                          ]
                        : []),
                    ...(admin.can_open
                        ? [
                              {
                                  id: 'exam-session-open',
                                  label: a.open,
                                  icon: PlayCircle,
                                  iconTone: 'ok' as const,
                                  disabled:
                                      busy ||
                                      target?.status !== SESSION_SCHEDULED,
                                  onSelect: () =>
                                      target && void transition(target, 'open'),
                              },
                          ]
                        : []),
                    ...(admin.can_close
                        ? [
                              {
                                  id: 'exam-session-close',
                                  label: a.close,
                                  icon: StopCircle,
                                  iconTone: 'danger' as const,
                                  disabled:
                                      busy ||
                                      target?.status !== SESSION_IN_PROGRESS,
                                  onSelect: () => target && setClosing(target),
                              },
                          ]
                        : []),
                ],
            },
            ...(admin.can_create_session
                ? [
                      {
                          id: 'exam-actions',
                          label: i18n.examisChrome.actions,
                          commands: [
                              {
                                  id: 'exam-session-add',
                                  label: a.newSession,
                                  icon: PlusCircle,
                                  iconTone: 'ok' as const,
                                  disabled: admin.subjects.length === 0,
                                  title:
                                      admin.subjects.length === 0
                                          ? a.noSubjects
                                          : a.newSession,
                                  onSelect: () => setDraft(EMPTY_SESSION),
                              },
                          ],
                      },
                  ]
                : []),
        ];

        return groups.filter((group) => group.commands.length > 0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target, busy, admin, seating.can_seat]);
    useExamisEditRibbon('exams', ribbon);

    useAttendanceMessages(
        admin.can_create_session && admin.subjects.length === 0
            ? [{ key: 'no-subjects', text: a.noSubjects, tone: 'warning' }]
            : [],
        exam.name,
    );

    const saveSession = async () => {
        if (draft === null) {
            return;
        }
        setBusy(true);
        // <input type="time"> gives HH:MM; the request expects HH:MM:SS.
        const ok = await request(
            `/exams/${exam.id}/sessions`,
            {
                ...draft,
                start_time: `${draft.start_time}:00`,
                end_time: `${draft.end_time}:00`,
            },
            'exam-session-create',
        );
        setBusy(false);
        if (ok) {
            setDraft(null);
        }
    };

    const submitSeating = async () => {
        if (!seatingFor || classId === '') {
            return;
        }
        setBusy(true);
        const ok = await request(
            `/exams/${exam.id}/sessions/${seatingFor.id}/seat`,
            {
                class_id: Number(classId),
                section_id: sectionId === '' ? null : Number(sectionId),
            },
            'exam-seat',
        );
        setBusy(false);
        if (ok) {
            setSeatingFor(null);
        }
    };

    const seatingSummary = (row: SessionRow) =>
        seating.result?.session_id !== row.id
            ? null
            : Object.entries(seating.result.counts).reduce<string>(
                  (text, [key, value]) =>
                      text.replace(`:${key}`, String(value)),
                  s.result,
              );

    const columns: AttendanceColumn<SessionRow>[] = [
        {
            id: 'date',
            header: i18n.attendance.date,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.session_date}</span>,
        },
        {
            id: 'subject',
            header: i18n.results.subject,
            kind: 'name',
            cell: (row) =>
                names.subjects[row.subject_id] ?? String(row.subject_id),
        },
        {
            id: 'time',
            header: i18n.common.time,
            kind: 'text',
            cell: (row) => (
                <span dir="ltr">
                    {row.start_time} – {row.end_time}
                </span>
            ),
        },
        {
            id: 'grades',
            header: `${a.maxGrade} / ${a.passGrade}`,
            kind: 'text',
            cell: (row) => (
                <span dir="ltr">
                    {row.max_grade} / {row.pass_grade}
                </span>
            ),
        },
        {
            id: 'seats',
            header: s.column,
            kind: 'num',
            cell: (row) => seating.seat_counts[row.id] ?? 0,
        },
        {
            id: 'status',
            header: i18n.common.status,
            kind: 'status',
            tone: (row) => STATUS_TONE[row.status] ?? 0,
            cell: (row) => statusChipLabel('exam', row.status),
        },
    ];

    const sections =
        seating.classes.find((c) => String(c.id) === classId)?.sections ?? [];
    const subjectOptions = admin.subjects.map((subject) => ({
        value: String(subject.id),
        label: subject.name,
    }));
    const classOptions = seating.classes.map((c) => ({
        value: String(c.id),
        label: c.name,
    }));
    const sectionOptions = [
        { value: '', label: s.allSections },
        ...sections.map((section) => ({
            value: String(section.id),
            label: section.name,
        })),
    ];
    const draftReady =
        draft !== null &&
        draft.subject_id !== '' &&
        draft.session_date !== '' &&
        draft.start_time !== '' &&
        draft.end_time !== '' &&
        draft.max_grade !== '' &&
        draft.pass_grade !== '';

    return (
        <ExamisPage title={exam.name} windowId="exams.show">
            <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div className="flex gap-1">
                    <dt className="font-semibold">
                        {i18n.exams.academicYear}:
                    </dt>
                    <dd>
                        {names.academic_years[exam.academic_year_id] ??
                            exam.academic_year_id}
                    </dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.exams.term}:</dt>
                    <dd>{names.terms[exam.term_id] ?? exam.term_id}</dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.exams.dates}:</dt>
                    <dd dir="ltr">
                        {exam.start_date} – {exam.end_date}
                    </dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.common.status}:</dt>
                    <dd>{statusChipLabel('exam', exam.status)}</dd>
                </div>
            </dl>
            {rows.map((row) => {
                const summary = seatingSummary(row);

                return summary ? (
                    <p
                        key={row.id}
                        className="text-sm"
                        role="status"
                        data-seating-result={row.id}
                    >
                        <span dir="ltr">{row.session_date}</span> — {summary}
                    </p>
                ) : null;
            })}
            <AttendanceTable
                caption={i18n.exams.sessionsCaption}
                storageKey="exams.sessions"
                rows={rows}
                rowKey={(row) => row.id}
                columns={columns}
                selection={selection}
                onOpen={(row) => router.visit(rosterHref(row))}
                emptyText={i18n.exams.noSessionsDesc}
            />

            {draft !== null ? (
                <AttendanceWindow
                    title={a.newSession}
                    columns={2}
                    pending={busy}
                    confirmLabel={a.addSession}
                    confirmDisabled={!draftReady}
                    onConfirm={() => void saveSession()}
                    onClose={() => setDraft(null)}
                    sections={[
                        {
                            id: 'exam-session-create',
                            title: a.newSession,
                            content: (
                                <>
                                    <RegistryListField
                                        label={a.subject}
                                        editing
                                        required
                                        fieldClassName="sis-branches-field--wide"
                                        value={draft.subject_id}
                                        display={
                                            subjectOptions.find(
                                                (o) =>
                                                    o.value ===
                                                    draft.subject_id,
                                            )?.label ?? '—'
                                        }
                                        options={subjectOptions}
                                        onChange={(subject_id) =>
                                            setDraft({ ...draft, subject_id })
                                        }
                                    />
                                    <RegistryTextField
                                        label={a.sessionDate}
                                        editing
                                        required
                                        type="date"
                                        dir="ltr"
                                        value={draft.session_date}
                                        onChange={(session_date) =>
                                            setDraft({ ...draft, session_date })
                                        }
                                    />
                                    <WindowTimeField
                                        label={a.startTime}
                                        value={draft.start_time}
                                        onChange={(start_time) =>
                                            setDraft({ ...draft, start_time })
                                        }
                                    />
                                    <WindowTimeField
                                        label={a.endTime}
                                        value={draft.end_time}
                                        onChange={(end_time) =>
                                            setDraft({ ...draft, end_time })
                                        }
                                    />
                                    <RegistryTextField
                                        label={a.maxGrade}
                                        editing
                                        required
                                        type="number"
                                        dir="ltr"
                                        value={draft.max_grade}
                                        onChange={(max_grade) =>
                                            setDraft({ ...draft, max_grade })
                                        }
                                    />
                                    <RegistryTextField
                                        label={a.passGrade}
                                        editing
                                        required
                                        type="number"
                                        dir="ltr"
                                        value={draft.pass_grade}
                                        onChange={(pass_grade) =>
                                            setDraft({ ...draft, pass_grade })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {seatingFor !== null ? (
                <AttendanceWindow
                    title={`${s.title} — ${seatingFor.session_date}`}
                    pending={busy}
                    confirmLabel={s.submit}
                    confirmDisabled={classId === ''}
                    onConfirm={() => void submitSeating()}
                    onClose={() => setSeatingFor(null)}
                    sections={[
                        {
                            id: 'exam-session-seat',
                            title: s.hint,
                            content:
                                seating.classes.length === 0 ? (
                                    <p className="text-sm">{s.noClasses}</p>
                                ) : (
                                    <>
                                        <RegistryListField
                                            label={s.class}
                                            editing
                                            required
                                            value={classId}
                                            display={
                                                classOptions.find(
                                                    (o) => o.value === classId,
                                                )?.label ?? '—'
                                            }
                                            options={classOptions}
                                            onChange={(value) => {
                                                setClassId(value);
                                                setSectionId('');
                                            }}
                                        />
                                        <RegistryListField
                                            label={s.section}
                                            editing
                                            value={sectionId}
                                            display={
                                                sectionOptions.find(
                                                    (o) =>
                                                        o.value === sectionId,
                                                )?.label ?? s.allSections
                                            }
                                            options={sectionOptions}
                                            onChange={setSectionId}
                                        />
                                    </>
                                ),
                        },
                    ]}
                />
            ) : null}

            <ConfirmDialog
                open={closing !== null}
                title={a.closeConfirmTitle}
                description={a.closeConfirmDesc}
                confirmLabel={a.close}
                confirmPending={busy}
                tone="danger"
                onConfirm={() => closing && void transition(closing, 'close')}
                onOpenChange={(open) => {
                    if (!open) {
                        setClosing(null);
                    }
                }}
            />
        </ExamisPage>
    );
}
