import { router, usePage } from '@inertiajs/react';
import {
    CheckCheck,
    ClipboardCheck,
    History,
    Info,
    ListOrdered,
    RotateCcw,
    Save,
    Send,
    ShieldCheck,
    Undo2,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    AttendancePage,
    AttendanceTable,
    AttendanceWindow,
    WindowFact,
    WindowTextArea,
    selectionGroup,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceMessages,
    useAttendanceSearch,
    useAttendanceSelection,
    useRowViewer,
    type AttendanceColumn,
    type AttendanceMessage,
} from '@/components/attendance/attendance-chrome';
import {
    RECORD_STATUSES,
    StatusLabel,
    UNMARKED,
    dateTime,
    newKey,
    recordLabel,
    recordStatusMeta,
    sheetLabel,
    sheetStatusMeta,
    statusCommands,
    toggleIn,
    type AttendanceAbilities,
    type StatusCounts,
} from '@/components/attendance/attendance-ui';
import {
    RegistryListField,
    RegistryTextField,
} from '@/components/organization/registry-sheet';
import type { ContextMenuItem } from '@/components/sis/context-menu';
import { usePageError } from '@/components/sis/page-error-context';
import type { PageRibbonGroup } from '@/components/sis/page-ribbon-context';
import { dayOfWeekLabel } from '@/components/sis/status-chip';
import { Button } from '@/components/ui/button';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import {
    queueDraft,
    readQueued,
    registerAttendanceServiceWorker,
    removeQueued,
    syncDraft,
    type QueuedDraft,
} from '@/lib/attendance-offline';
import type { BreadcrumbItem } from '@/types';

type Row = {
    enrollment_id: number;
    student_id: number;
    student_code: string;
    full_name: string;
    status: number;
    minutes_late: number | null;
    note: string | null;
    updated_at: string | null;
    excuse_id: number | null;
};

type SheetEvent = {
    id: number;
    event_type: number;
    version: number;
    note: string | null;
    created_at: string;
    actor_name: string | null;
};

type Header = {
    id: number;
    academic_year_id: number;
    session_date: string;
    period_number: number | null;
    start_time: string | null;
    end_time: string | null;
    subject_name: string | null;
    class_name: string | null;
    section_name: string | null;
    group_name: string | null;
    room_name: string | null;
    teacher_name: string | null;
    lesson_type: number | null;
    origin: number;
    sheet_status: number;
    status: number;
    version: number;
    submission_count: number;
    submitted_at: string | null;
    submitted_late: boolean;
    reviewed_at: string | null;
    review_note: string | null;
    reviewed_by_name: string | null;
};

type RecordRow = Record<string, string | number | null>;

type Props = {
    sheet: {
        session: Header;
        rows: Row[];
        off_roster: Array<{
            student_code: string;
            full_name: string;
            status: number;
        }>;
        counts: StatusCounts & { expected: number; unmarked: number };
        events: SheetEvent[];
        policy: {
            default_mark: number;
            require_complete: boolean;
            teacher_can_mark_excused: boolean;
            late_grace_minutes: number;
            late_as_absent_minutes: number | null;
        };
        window: string;
        permissions: {
            can_edit: boolean;
            can_submit: boolean;
            can_mark_excused: boolean;
            review_actions: string[];
            can_correct: boolean;
            acting_on_behalf: boolean;
        };
    };
    student_record?: {
        student_id: number;
        all_subjects: boolean;
        rows: RecordRow[];
        meta: { total: number };
        truncated: boolean;
    } | null;
    abilities: AttendanceAbilities;
};

type Mark = { status: number; minutes_late: number | null; note: string };
type Marks = Record<number, Mark>;
type ReviewAction = 'start_review' | 'approve' | 'return' | 'reject';
type Sync = 'idle' | 'queued' | 'syncing' | 'conflict' | 'stale';
type StatusEdit = {
    ids: number[];
    status: string;
    minutes_late: string;
    note: string;
    keepNote: boolean;
};

function baselineOf(rows: Row[]): Marks {
    return Object.fromEntries(
        rows.map((r) => [
            r.enrollment_id,
            {
                status: r.status,
                minutes_late: r.minutes_late,
                note: r.note ?? '',
            },
        ]),
    );
}

function sameMark(a: Mark | undefined, b: Mark | undefined): boolean {
    return (
        a !== undefined &&
        b !== undefined &&
        a.status === b.status &&
        (a.minutes_late ?? null) === (b.minutes_late ?? null) &&
        a.note.trim() === b.note.trim()
    );
}

export default function AttendanceSheet(props: Props) {
    const a = t().attendanceSystem;
    const s = props.sheet.session;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t().attendance.title, href: '/attendance' },
        { title: a.myDay.title, href: '/attendance/my' },
        {
            title: `${a.sheet.title} ${s.id}`,
            href: `/attendance/sheets/${s.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SheetPage {...props} />
        </AppLayout>
    );
}

function SheetPage({ sheet, student_record, abilities }: Props) {
    const a = t().attendanceSystem;
    const sh = a.sheet;
    const c = a.chrome;
    const session = sheet.session;
    const perms = sheet.permissions;
    const page = usePage().props as { flash?: { error?: string | null } };
    const { showInertiaErrors, showError } = usePageError();

    const baseline = useMemo(() => baselineOf(sheet.rows), [sheet.rows]);
    const initialMarks = useCallback(
        (): Marks =>
            perms.can_edit && sheet.policy.default_mark === 1
                ? Object.fromEntries(
                      Object.entries(baseline).map(([id, m]) => [
                          id,
                          m.status === 0 ? { ...m, status: 1 } : m,
                      ]),
                  )
                : baseline,
        [baseline, perms.can_edit, sheet.policy.default_mark],
    );

    const [marks, setMarks] = useState<Marks>(initialMarks);
    const [undoStack, setUndoStack] = useState<Marks[]>([]);
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<number[]>([]);
    const [busy, setBusy] = useState<
        'idle' | 'saving' | 'submitting' | 'reviewing'
    >('idle');
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const [sync, setSync] = useState<Sync>('idle');
    const [, setQueued] = useState<QueuedDraft | null>(null);
    const [submitOpen, setSubmitOpen] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [review, setReview] = useState<{
        action: ReviewAction;
        reason: string;
    } | null>(null);
    const [statusEdit, setStatusEdit] = useState<StatusEdit | null>(null);
    const [correction, setCorrection] = useState<{
        row: Row;
        status: string;
        reason: string;
    } | null>(null);
    const [infoOpen, setInfoOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [recordFor, setRecordFor] = useState<Row | null>(null);
    const saveKeyRef = useRef<{ key: string; payload: string } | null>(null);

    // A new server version becomes the baseline.
    useEffect(() => {
        setMarks(initialMarks());
        setUndoStack([]);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [session.version, sheet.rows]);

    const changed = useMemo(
        () =>
            sheet.rows.filter(
                (r) =>
                    !sameMark(
                        marks[r.enrollment_id],
                        baseline[r.enrollment_id],
                    ),
            ),
        [sheet.rows, marks, baseline],
    );
    const dirty = changed.length > 0;
    const payloadMarks = useCallback(
        () =>
            changed.map((r) => ({
                enrollment_id: r.enrollment_id,
                status: marks[r.enrollment_id].status,
                minutes_late: marks[r.enrollment_id].minutes_late,
                note: marks[r.enrollment_id].note.trim() || null,
            })),
        [changed, marks],
    );

    // A stale version (edited elsewhere): an error dialog with «إعادة التحميل».
    useEffect(() => {
        if (page.flash?.error === 'attendance.sheet_version_conflict') {
            showError({
                description: sh.conflict,
                action: { label: sh.reload, onSelect: () => router.reload() },
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.flash?.error]);

    // ── Offline queue (ADR-023) ─────────────────────────────────────────────────────
    const flushQueue = useCallback(async () => {
        const entry = await readQueued(session.id);
        if (entry === null) {
            setQueued(null);
            setSync('idle');

            return;
        }
        setQueued(entry);
        if (entry.version !== session.version) {
            setSync('stale');

            return;
        }
        if (!navigator.onLine) {
            setSync('queued');

            return;
        }
        setSync('syncing');
        const outcome = await syncDraft(entry);
        if (outcome.ok) {
            setQueued(null);
            setSync('idle');
            router.reload({ only: ['sheet'] });
        } else {
            setSync(outcome.conflict ? 'conflict' : 'queued');
        }
    }, [session.id, session.version]);

    useEffect(() => {
        registerAttendanceServiceWorker();
        void readQueued(session.id).then((entry) => {
            if (
                entry !== null &&
                entry.version === session.version &&
                perms.can_edit
            ) {
                setMarks((current) => {
                    const next = { ...current };
                    entry.marks.forEach(
                        (m) =>
                            (next[m.enrollment_id] = {
                                status: m.status,
                                minutes_late: m.minutes_late,
                                note: m.note ?? '',
                            }),
                    );

                    return next;
                });
            }
            void flushQueue();
        });
        const up = () => {
            setOnline(true);
            void flushQueue();
        };
        const down = () => setOnline(false);
        const fromWorker = (event: MessageEvent) =>
            event.data?.type === 'sis-attendance-synced' && void flushQueue();
        window.addEventListener('online', up);
        window.addEventListener('offline', down);
        navigator.serviceWorker?.addEventListener('message', fromWorker);

        return () => {
            window.removeEventListener('online', up);
            window.removeEventListener('offline', down);
            navigator.serviceWorker?.removeEventListener('message', fromWorker);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [session.id, session.version]);

    // A queued draft that the server no longer accepts: an error dialog (reload / discard the local copy).
    useEffect(() => {
        if (sync === 'conflict' || sync === 'stale') {
            showError({
                description: sh.queuedConflict,
                action: {
                    label: sh.discardLocal,
                    onSelect: () =>
                        void removeQueued(session.id).then(() => {
                            setQueued(null);
                            setSync('idle');
                            router.reload();
                        }),
                },
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sync]);

    const storeOffline = useCallback(
        async (key: string) => {
            const ok = await queueDraft({
                session_id: session.id,
                version: session.version,
                marks: payloadMarks(),
                key,
            });
            setSync(ok ? 'queued' : 'idle');
            if (ok) setQueued(await readQueued(session.id));
        },
        [payloadMarks, session.id, session.version],
    );

    // Leaving with unsaved marks asks first.
    useEffect(() => {
        const offBefore = router.on('before', (event) => {
            const method = (event.detail.visit.method ?? 'get').toLowerCase();
            const partial = (event.detail.visit.only ?? []).length > 0;
            // Leaving with unsaved marks: no question — they are kept on this device and sent as a draft
            // (same version + idempotency key) by the background sync; nothing is lost, nothing is overwritten.
            if (dirty && sync !== 'queued' && method === 'get' && !partial) {
                void storeOffline(newKey('draft-leave'));
            }
        });
        const onUnload = (event: BeforeUnloadEvent) => {
            if (dirty && sync !== 'queued') event.preventDefault();
        };
        window.addEventListener('beforeunload', onUnload);

        return () => {
            offBefore();
            window.removeEventListener('beforeunload', onUnload);
        };
    }, [dirty, sync, storeOffline]);

    const send = useCallback(
        (kind: 'draft' | 'submit') => {
            const body = {
                version: session.version,
                marks: payloadMarks(),
                ...(kind === 'submit' ? { confirm: true } : {}),
            };
            const signature = `${kind}:${JSON.stringify(body)}`;
            if (
                saveKeyRef.current === null ||
                saveKeyRef.current.payload !== signature
            ) {
                saveKeyRef.current = { key: newKey(kind), payload: signature };
            }
            const key = saveKeyRef.current.key;
            if (kind === 'draft' && !navigator.onLine) {
                void storeOffline(key);

                return;
            }
            setBusy(kind === 'draft' ? 'saving' : 'submitting');
            router.post(`/attendance/sheets/${session.id}/${kind}`, body, {
                preserveScroll: true,
                preserveState: true,
                headers: { 'X-Idempotency-Key': key },
                onSuccess: () => {
                    void removeQueued(session.id);
                    setSync('idle');
                    if (kind === 'submit') {
                        setSubmitOpen(false);
                        setConfirmed(false);
                    }
                },
                onError: (errors) => showInertiaErrors(errors),
                onNetworkError: () => {
                    setOnline(false);
                    if (kind === 'draft') void storeOffline(key);

                    return false;
                },
                onFinish: () => setBusy('idle'),
            });
        },
        [
            payloadMarks,
            session.id,
            session.version,
            showInertiaErrors,
            storeOffline,
        ],
    );

    const apply = (next: (current: Marks) => Marks) => {
        if (!perms.can_edit) return;
        setUndoStack((stack) => [...stack.slice(-19), marks]);
        setMarks(next);
    };
    const setStatus = (
        ids: number[],
        status: number,
        extra?: { minutes_late?: number | null; note?: string },
    ) =>
        apply((current) => {
            const next = { ...current };
            ids.forEach(
                (id) =>
                    (next[id] = {
                        ...current[id],
                        status,
                        minutes_late:
                            status === 3
                                ? extra?.minutes_late !== undefined
                                    ? extra.minutes_late
                                    : (current[id]?.minutes_late ?? null)
                                : null,
                        note:
                            extra?.note !== undefined
                                ? extra.note
                                : (current[id]?.note ?? ''),
                    }),
            );

            return next;
        });
    const undo = () => {
        const previous = undoStack[undoStack.length - 1];
        if (previous !== undefined) {
            setMarks(previous);
            setUndoStack((stack) => stack.slice(0, -1));
        }
    };
    const reset = () => apply(() => baseline);

    const allowedStatuses = RECORD_STATUSES.filter(
        (st) =>
            st.value !== 4 ||
            perms.can_mark_excused ||
            sheet.policy.teacher_can_mark_excused,
    );
    const counts = useMemo(() => {
        const out: Record<number, number> = {
            0: 0,
            1: 0,
            2: 0,
            3: 0,
            4: 0,
            5: 0,
        };
        sheet.rows.forEach(
            (r) => (out[marks[r.enrollment_id]?.status ?? 0] += 1),
        );

        return out;
    }, [sheet.rows, marks]);

    const visibleRows = useMemo(() => {
        const q = query.trim().toLocaleLowerCase('ar');

        return sheet.rows.filter((r) => {
            const st = marks[r.enrollment_id]?.status ?? 0;

            return (
                (statusFilter.length === 0 || statusFilter.includes(st)) &&
                (q === '' ||
                    r.full_name.toLocaleLowerCase('ar').includes(q) ||
                    r.student_code.toLocaleLowerCase('ar').includes(q))
            );
        });
    }, [sheet.rows, marks, statusFilter, query]);

    const selection = useAttendanceSelection(
        visibleRows,
        (r) => r.enrollment_id,
    );
    const targets = selection.targets;
    const single = targets.length === 1 ? targets[0] : null;

    const runReview = () => {
        if (review === null) return;
        setBusy('reviewing');
        router.post(
            `/attendance/sheets/${session.id}/review`,
            {
                action: review.action,
                version: session.version,
                reason: review.reason.trim() || null,
            },
            {
                preserveScroll: true,
                headers: {
                    'X-Idempotency-Key': newKey(`review-${review.action}`),
                },
                onSuccess: () => setReview(null),
                onError: (e) => showInertiaErrors(e),
                onFinish: () => setBusy('idle'),
            },
        );
    };
    const runCorrection = () => {
        if (correction === null) return;
        setBusy('reviewing');
        router.post(
            `/attendance/sheets/${session.id}/students/${correction.row.student_id}/correct`,
            {
                academic_year_id: session.academic_year_id,
                new_status: Number(correction.status),
                reason: correction.reason.trim(),
            },
            {
                preserveScroll: true,
                headers: { 'X-Idempotency-Key': newKey('correct') },
                onSuccess: () => setCorrection(null),
                onError: (e) => showInertiaErrors(e),
                onFinish: () => setBusy('idle'),
            },
        );
    };
    const openCorrection = (row: Row) =>
        setCorrection({ row, status: String(row.status || 1), reason: '' });

    /** «تحرير» of the selected students: status (+ minutes late, note) for one or several; an approved sheet → the official correction. */
    const edit = (rows: Row[]) => {
        if (perms.can_edit) {
            const first = marks[rows[0].enrollment_id];
            setStatusEdit({
                ids: rows.map((r) => r.enrollment_id),
                status: String(first?.status || 1),
                minutes_late:
                    rows.length === 1 && first?.minutes_late != null
                        ? String(first.minutes_late)
                        : '',
                note: rows.length === 1 ? (first?.note ?? '') : '',
                keepNote: rows.length > 1,
            });
        } else if (rows.length === 1) {
            openCorrection(rows[0]);
        }
    };
    const applyStatusEdit = () => {
        if (statusEdit === null) return;
        const status = Number(statusEdit.status);
        setStatus(statusEdit.ids, status, {
            minutes_late:
                status === 3 && statusEdit.minutes_late !== ''
                    ? Math.max(0, Number(statusEdit.minutes_late))
                    : null,
            ...(statusEdit.keepNote && statusEdit.note.trim() === ''
                ? {}
                : { note: statusEdit.note }),
        });
        setStatusEdit(null);
    };

    /** «سجل الطالب التفصيلي»: loaded on demand from the server (partial reload), shown in a window. */
    const openRecord = (row: Row) => {
        setRecordFor(row);
        router.reload({
            only: ['student_record'],
            data: { student: row.student_id },
        });
    };

    const statusOf = (r: Row) => marks[r.enrollment_id]?.status ?? 0;
    const columns: AttendanceColumn<Row>[] = [
        {
            id: 'code',
            header: sh.code,
            kind: 'text',
            cell: (r) => <span dir="ltr">{r.student_code}</span>,
        },
        {
            id: 'name',
            header: sh.student,
            kind: 'name',
            cell: (r) =>
                `${r.full_name}${sameMark(marks[r.enrollment_id], baseline[r.enrollment_id]) ? '' : ' •'}`,
        },
        {
            id: 'status',
            header: sh.status,
            kind: 'status',
            tone: (r) => recordStatusMeta(statusOf(r)).tone,
            iconTone: (r) => recordStatusMeta(statusOf(r)).iconTone,
            cell: (r) => (
                <StatusLabel
                    meta={recordStatusMeta(statusOf(r))}
                    label={recordLabel(statusOf(r))}
                />
            ),
            text: (r) => recordLabel(statusOf(r)),
        },
        ...(perms.can_edit
            ? [
                  {
                      id: 'mark',
                      header: sh.markSelected,
                      kind: 'plain' as const,
                      text: (r: Row) => recordLabel(statusOf(r)),
                      cell: (r: Row) => (
                          <span
                              role="radiogroup"
                              aria-label={`${sh.status}: ${r.full_name}`}
                              className="sis-att-mark"
                              onClick={(event) => event.stopPropagation()}
                              onDoubleClick={(event) => event.stopPropagation()}
                          >
                              {allowedStatuses.map((st) => {
                                  const Icon = st.icon;

                                  return (
                                      <button
                                          key={st.value}
                                          type="button"
                                          role="radio"
                                          className="sis-att-mark__btn"
                                          data-status={st.value}
                                          aria-checked={
                                              statusOf(r) === st.value
                                          }
                                          title={recordLabel(st.value)}
                                          aria-label={recordLabel(st.value)}
                                          onClick={() =>
                                              setStatus(
                                                  [r.enrollment_id],
                                                  st.value,
                                              )
                                          }
                                      >
                                          <Icon aria-hidden />
                                      </button>
                                  );
                              })}
                          </span>
                      ),
                  },
              ]
            : []),
        {
            id: 'late',
            header: sh.minutesLate,
            kind: perms.can_edit ? 'plain' : 'num',
            text: (r) => marks[r.enrollment_id]?.minutes_late ?? '—',
            cell: (r) =>
                perms.can_edit ? (
                    <input
                        type="number"
                        min={0}
                        max={600}
                        dir="ltr"
                        className="sis-att-cell-input"
                        style={{ width: '5rem' }}
                        aria-label={`${sh.minutesLate}: ${r.full_name}`}
                        value={marks[r.enrollment_id]?.minutes_late ?? ''}
                        onClick={(event) => event.stopPropagation()}
                        onDoubleClick={(event) => event.stopPropagation()}
                        onChange={(event) => {
                            const raw = event.target.value;
                            const minutes =
                                raw === ''
                                    ? null
                                    : Math.max(
                                          0,
                                          Math.min(
                                              600,
                                              Math.trunc(Number(raw)),
                                          ),
                                      );
                            // Minutes late make the student «متأخر»; clearing them keeps the status.
                            apply((cur) => ({
                                ...cur,
                                [r.enrollment_id]: {
                                    ...cur[r.enrollment_id],
                                    minutes_late: minutes,
                                    status:
                                        minutes !== null && minutes > 0
                                            ? 3
                                            : cur[r.enrollment_id].status,
                                },
                            }));
                        }}
                    />
                ) : (
                    <span dir="ltr">
                        {marks[r.enrollment_id]?.minutes_late ?? '—'}
                    </span>
                ),
        },
        {
            id: 'note',
            header: sh.note,
            kind: perms.can_edit ? 'plain' : 'text',
            text: (r) => (marks[r.enrollment_id]?.note ?? '').trim() || '—',
            cell: (r) =>
                perms.can_edit ? (
                    <input
                        type="text"
                        maxLength={500}
                        className="sis-att-cell-input"
                        style={{ minWidth: '11rem' }}
                        aria-label={`${sh.note}: ${r.full_name}`}
                        value={marks[r.enrollment_id]?.note ?? ''}
                        onClick={(event) => event.stopPropagation()}
                        onDoubleClick={(event) => event.stopPropagation()}
                        onChange={(event) => {
                            const note = event.target.value;
                            apply((cur) => ({
                                ...cur,
                                [r.enrollment_id]: {
                                    ...cur[r.enrollment_id],
                                    note,
                                },
                            }));
                        }}
                    />
                ) : (
                    (marks[r.enrollment_id]?.note ?? '').trim() || '—'
                ),
        },
        {
            id: 'updated',
            header: sh.lastChange,
            kind: 'text',
            cell: (r) => dateTime(r.updated_at),
        },
    ];
    const viewer = useRowViewer(
        columns,
        (r) => r.full_name,
        (r) => (
            <div className="sis-admission-sheet__field sis-branches-field--wide sis-attendance-window-actions">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => openRecord(r)}
                >
                    <History aria-hidden />
                    {a.reports.types.student_detail}
                </Button>
                {perms.can_edit || perms.can_correct ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => edit([r])}
                    >
                        <ShieldCheck aria-hidden />
                        {perms.can_edit ? c.edit : sh.correct}
                    </Button>
                ) : null}
            </div>
        ),
    );

    // ── Ribbon «تحرير» ──────────────────────────────────────────────────────────────
    const saveTitle =
        sync === 'queued'
            ? sh.savedOnDevice
            : sync === 'syncing'
              ? sh.retrying
              : !online
                ? sh.offline
                : dirty
                  ? `${sh.unsaved} (${changed.length})`
                  : sh.upToDate;
    const editGroups = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [
            selectionGroup(
                'attendance-sheet-selection',
                targets,
                viewer.open,
                perms.can_edit || (perms.can_correct && targets.length === 1)
                    ? edit
                    : null,
                {
                    editLabel: perms.can_edit ? c.edit : sh.correct,
                    extra: [
                        {
                            id: 'attendance-sheet-record',
                            label: a.reports.types.student_detail,
                            icon: History,
                            iconTone: 'education',
                            disabled: single === null,
                            title:
                                single === null ? c.needsSelection : undefined,
                            onSelect: () => single && openRecord(single),
                        },
                    ],
                },
            ),
        ];
        if (perms.can_edit) {
            groups.push(
                {
                    id: 'attendance-sheet-actions',
                    label: sh.title,
                    commands: [
                        {
                            id: 'attendance-sheet-save',
                            label: busy === 'saving' ? sh.saving : sh.saveDraft,
                            icon: Save,
                            iconTone: 'sky',
                            tone: 'save',
                            title: saveTitle,
                            count: dirty ? changed.length : undefined,
                            disabled: !dirty || busy !== 'idle',
                            onSelect: () => send('draft'),
                        },
                        {
                            id: 'attendance-sheet-submit',
                            label:
                                busy === 'submitting'
                                    ? sh.submitting
                                    : sh.submit,
                            icon: Send,
                            iconTone: 'ok',
                            disabled:
                                busy !== 'idle' ||
                                !online ||
                                sheet.rows.length === 0,
                            title: online ? sh.submit : sh.offline,
                            onSelect: () => setSubmitOpen(true),
                        },
                        {
                            id: 'attendance-sheet-undo',
                            label: sh.undo,
                            icon: Undo2,
                            iconTone: 'steel',
                            disabled: undoStack.length === 0,
                            onSelect: undo,
                        },
                        {
                            id: 'attendance-sheet-reset',
                            label: sh.reset,
                            icon: RotateCcw,
                            iconTone: 'warning',
                            disabled: !dirty,
                            onSelect: reset,
                        },
                        {
                            id: 'attendance-sheet-all-present',
                            label: sh.allPresent,
                            icon: CheckCheck,
                            iconTone: 'ok',
                            disabled: sheet.rows.length === 0,
                            onSelect: () =>
                                setStatusEdit({
                                    ids: sheet.rows.map((r) => r.enrollment_id),
                                    status: '1',
                                    minutes_late: '',
                                    note: '',
                                    keepNote: true,
                                }),
                        },
                        {
                            id: 'attendance-sheet-all-absent',
                            label: sh.allAbsent,
                            icon: XCircle,
                            iconTone: 'danger',
                            disabled: sheet.rows.length === 0,
                            onSelect: () =>
                                setStatusEdit({
                                    ids: sheet.rows.map((r) => r.enrollment_id),
                                    status: '2',
                                    minutes_late: '',
                                    note: '',
                                    keepNote: true,
                                }),
                        },
                    ],
                },
                {
                    id: 'attendance-sheet-mark',
                    label: sh.markSelected,
                    commands: allowedStatuses.map((st) => ({
                        id: `attendance-sheet-mark-${st.value}`,
                        label: recordLabel(st.value),
                        icon: st.icon,
                        iconTone: st.iconTone,
                        title:
                            targets.length === 0
                                ? c.needsSelection
                                : `${sh.markSelected} (${targets.length})`,
                        disabled: targets.length === 0,
                        onSelect: () =>
                            setStatus(
                                targets.map((r) => r.enrollment_id),
                                st.value,
                            ),
                    })),
                },
            );
        }
        if (perms.review_actions.length > 0) {
            const meta: Record<
                string,
                {
                    label: string;
                    icon: typeof Send;
                    tone: 'amber' | 'ok' | 'warning' | 'danger';
                }
            > = {
                start_review: {
                    label: sh.startReview,
                    icon: ClipboardCheck,
                    tone: 'amber',
                },
                approve: { label: sh.approve, icon: ShieldCheck, tone: 'ok' },
                return: { label: sh.return, icon: Undo2, tone: 'warning' },
                reject: { label: sh.reject, icon: XCircle, tone: 'danger' },
            };
            groups.push({
                id: 'attendance-sheet-review',
                label: a.review.title,
                commands: perms.review_actions.map((action) => ({
                    id: `attendance-sheet-review-${action}`,
                    label: meta[action].label,
                    icon: meta[action].icon,
                    iconTone: meta[action].tone,
                    disabled: busy !== 'idle' || !online,
                    onSelect: () =>
                        setReview({
                            action: action as ReviewAction,
                            reason: '',
                        }),
                })),
            });
        }
        groups.push({
            id: 'attendance-sheet-info',
            label: sh.title,
            commands: [
                {
                    id: 'attendance-sheet-info-open',
                    label: sh.info,
                    icon: Info,
                    iconTone: 'sky',
                    onSelect: () => setInfoOpen(true),
                },
                {
                    id: 'attendance-sheet-history',
                    label: sh.history,
                    icon: ListOrdered,
                    iconTone: 'steel',
                    count: sheet.events.length,
                    onSelect: () => setHistoryOpen(true),
                },
            ],
        });
        groups.push({
            id: 'attendance-sheet-filter',
            label: c.statuses,
            commands: statusCommands(
                'attendance-sheet-status',
                [...RECORD_STATUSES, UNMARKED],
                a.recordStatus,
                counts,
                statusFilter,
                (v) => setStatusFilter((cur) => toggleIn(cur, v)),
            ),
        });

        return groups;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        perms,
        dirty,
        busy,
        online,
        sync,
        undoStack.length,
        targets,
        counts,
        statusFilter,
        allowedStatuses.length,
        sheet.events.length,
    ]);
    useAttendanceEditRibbon('my', abilities, editGroups);

    useAttendanceHomeFilters(
        [
            {
                key: 'status',
                type: 'multi' as const,
                label: sh.status,
                allLabel: c.allStatuses,
                values: statusFilter.map(String),
                options: [...RECORD_STATUSES, UNMARKED].map((st) => ({
                    value: String(st.value),
                    label: recordLabel(st.value),
                })),
                onChange: (values: string[]) =>
                    setStatusFilter(values.map(Number)),
            },
        ],
        statusFilter.length > 0 ? () => setStatusFilter([]) : null,
        [statusFilter],
    );
    useAttendanceSearch(query, sh.search, setQuery, setQuery);

    // Guidance / warnings of this sheet — in the message dialog (never as text on the page).
    const messages: AttendanceMessage[] = [
        ...(sheet.rows.length === 0
            ? [
                  {
                      key: 'no-roster',
                      tone: 'warning' as const,
                      text: sh.noRoster,
                  },
              ]
            : []),
        ...(session.review_note && session.sheet_status === 5
            ? [
                  {
                      key: 'returned',
                      tone: 'warning' as const,
                      text: `${sh.reviewNote}: ${session.review_note}`,
                  },
              ]
            : []),
    ];
    useAttendanceMessages(messages, `${sh.title} ${session.id}`);

    const menuItems = (row: Row) => {
        const items: ContextMenuItem[] = perms.can_edit
            ? allowedStatuses.map((st) => ({
                  id: `st-${st.value}`,
                  label: recordLabel(st.value),
                  icon: st.icon,
                  onSelect: () => setStatus([row.enrollment_id], st.value),
              }))
            : [];
        items.push({
            id: 'view',
            label: c.view,
            icon: Info,
            separator: items.length > 0,
            onSelect: () => viewer.open([row]),
        });
        if (perms.can_edit || perms.can_correct)
            items.push({
                id: 'edit',
                label: perms.can_edit ? c.edit : sh.correct,
                icon: ShieldCheck,
                onSelect: () => edit([row]),
            });
        items.push({
            id: 'record',
            label: a.reports.types.student_detail,
            icon: History,
            onSelect: () => openRecord(row),
        });

        return items;
    };

    const facts = `${dayOfWeekLabel(new Date(`${session.session_date}T12:00:00Z`).getUTCDay() + 1)} ${session.session_date} · ${sh.lesson} ${session.period_number ?? '—'} (${session.start_time ?? '—'}–${session.end_time ?? '—'})`;
    const recordRows =
        student_record &&
        recordFor &&
        student_record.student_id === recordFor.student_id
            ? student_record.rows
            : null;
    const recordTotals = useMemo(() => {
        const out: Record<number, number> = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        (recordRows ?? []).forEach(
            (r) => (out[Number(r.status)] = (out[Number(r.status)] ?? 0) + 1),
        );

        return out;
    }, [recordRows]);

    return (
        <AttendancePage title={`${sh.title} ${session.id}`}>
            <AttendanceTable
                caption={sh.title}
                storageKey="sheet.roster"
                rows={visibleRows}
                rowKey={(r) => r.enrollment_id}
                columns={columns}
                emptyText={sheet.rows.length === 0 ? sh.noRoster : sh.noMatch}
                emptyAsDialog={sheet.rows.length > 0}
                selection={selection}
                onOpen={(r) => viewer.open([r])}
                menuItems={menuItems}
                menuLabel={sh.actionsFor}
            />

            {viewer.element}

            {submitOpen ? (
                <AttendanceWindow
                    title={sh.reviewTitle}
                    columns={2}
                    pending={busy === 'submitting'}
                    confirmLabel={sh.submit}
                    confirmDisabled={
                        !confirmed ||
                        (sheet.policy.require_complete && counts[0] > 0)
                    }
                    onConfirm={() => send('submit')}
                    onClose={() => {
                        setSubmitOpen(false);
                        setConfirmed(false);
                    }}
                    sections={[
                        {
                            id: 'att-submit-counts',
                            title: sh.summary,
                            content: (
                                <>
                                    <WindowFact
                                        label={sh.expected}
                                        value={
                                            <span dir="ltr">
                                                {sheet.rows.length}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={sh.unmarked}
                                        value={
                                            <span dir="ltr">{counts[0]}</span>
                                        }
                                    />
                                    {RECORD_STATUSES.map((st) => (
                                        <WindowFact
                                            key={st.value}
                                            label={recordLabel(st.value)}
                                            value={
                                                <span dir="ltr">
                                                    {counts[st.value]}
                                                </span>
                                            }
                                        />
                                    ))}
                                    <WindowFact
                                        label={sh.check}
                                        value={
                                            counts[0] > 0
                                                ? sh.reviewIncomplete.replace(
                                                      ':n',
                                                      String(counts[0]),
                                                  )
                                                : sh.reviewOk
                                        }
                                    />
                                    {sheet.window === 'late' ? (
                                        <WindowFact
                                            wide
                                            label={sh.lateSubmission}
                                            value={a.window.late}
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            id: 'att-submit-confirm',
                            title: sh.confirmTitle,
                            content: (
                                <label className="sis-admission-sheet__field sis-branches-field--wide sis-attendance-window-actions items-center">
                                    <input
                                        type="checkbox"
                                        checked={confirmed}
                                        onChange={(event) =>
                                            setConfirmed(event.target.checked)
                                        }
                                    />
                                    <span>{sh.reviewConfirm}</span>
                                </label>
                            ),
                        },
                    ]}
                />
            ) : null}

            {statusEdit !== null ? (
                <AttendanceWindow
                    title={
                        statusEdit.ids.length > 1
                            ? `${sh.markSelected} (${statusEdit.ids.length})`
                            : (sheet.rows.find(
                                  (r) => r.enrollment_id === statusEdit.ids[0],
                              )?.full_name ?? sh.markSelected)
                    }
                    columns={2}
                    confirmLabel={t().dialog.confirm}
                    onConfirm={applyStatusEdit}
                    onClose={() => setStatusEdit(null)}
                    sections={[
                        {
                            id: 'att-status-edit',
                            title: sh.status,
                            content: (
                                <>
                                    <RegistryListField
                                        label={sh.newStatus}
                                        editing
                                        required
                                        value={statusEdit.status}
                                        display={recordLabel(
                                            Number(statusEdit.status),
                                        )}
                                        options={allowedStatuses.map((st) => ({
                                            value: String(st.value),
                                            label: recordLabel(st.value),
                                        }))}
                                        onChange={(status) =>
                                            setStatusEdit({
                                                ...statusEdit,
                                                status,
                                            })
                                        }
                                    />
                                    {statusEdit.status === '3' ? (
                                        <RegistryTextField
                                            label={sh.minutesLate}
                                            editing
                                            type="number"
                                            dir="ltr"
                                            value={statusEdit.minutes_late}
                                            onChange={(minutes_late) =>
                                                setStatusEdit({
                                                    ...statusEdit,
                                                    minutes_late,
                                                })
                                            }
                                        />
                                    ) : (
                                        <WindowFact
                                            label={sh.expected}
                                            value={
                                                <span dir="ltr">
                                                    {statusEdit.ids.length}
                                                </span>
                                            }
                                        />
                                    )}
                                    <WindowTextArea
                                        label={
                                            statusEdit.keepNote
                                                ? sh.noteForAll
                                                : sh.note
                                        }
                                        value={statusEdit.note}
                                        onChange={(note) =>
                                            setStatusEdit({
                                                ...statusEdit,
                                                note,
                                            })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {infoOpen ? (
                <AttendanceWindow
                    title={`${sh.sheetNo} ${session.id}`}
                    columns={2}
                    onClose={() => setInfoOpen(false)}
                    sections={[
                        {
                            id: 'att-sheet-lesson',
                            title: sh.lesson,
                            content: (
                                <>
                                    <WindowFact
                                        label={sh.status}
                                        value={
                                            <StatusLabel
                                                meta={sheetStatusMeta(
                                                    session.sheet_status,
                                                )}
                                                label={sheetLabel(
                                                    session.sheet_status,
                                                )}
                                            />
                                        }
                                    />
                                    <WindowFact
                                        label={c.origin}
                                        value={c.origins[session.origin] ?? '—'}
                                    />
                                    <WindowFact
                                        wide
                                        label={a.myDay.date}
                                        value={facts}
                                    />
                                    <WindowFact
                                        label={a.myDay.subject}
                                        value={`${session.subject_name ?? '—'}${session.lesson_type ? ` · ${a.lessonType[session.lesson_type]}` : ''}`}
                                    />
                                    <WindowFact
                                        label={a.myDay.classSection}
                                        value={`${session.class_name ?? '—'} / ${session.section_name ?? '—'}${session.group_name ? ` · ${session.group_name}` : ''}`}
                                    />
                                    <WindowFact
                                        label={a.myDay.teacher}
                                        value={session.teacher_name ?? '—'}
                                    />
                                    <WindowFact
                                        label={a.myDay.room}
                                        value={session.room_name ?? '—'}
                                    />
                                </>
                            ),
                        },
                        {
                            id: 'att-sheet-flow',
                            title: sh.summary,
                            content: (
                                <>
                                    <WindowFact
                                        label={sh.expected}
                                        value={
                                            <span dir="ltr">
                                                {sheet.rows.length}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={sh.unmarked}
                                        value={
                                            <span dir="ltr">{counts[0]}</span>
                                        }
                                    />
                                    <WindowFact
                                        label={sh.submittedAt}
                                        value={`${dateTime(session.submitted_at)}${session.submitted_late ? ` · ${sh.lateSubmission}` : ''}`}
                                    />
                                    <WindowFact
                                        label={sh.reviewedAt}
                                        value={`${dateTime(session.reviewed_at)} ${session.reviewed_by_name ?? ''}`}
                                    />
                                    <WindowFact
                                        label={sh.version}
                                        value={
                                            <span dir="ltr">
                                                {session.version}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={a.window.open}
                                        value={
                                            a.window[sheet.window] ??
                                            sheet.window
                                        }
                                    />
                                    {session.review_note ? (
                                        <WindowFact
                                            wide
                                            label={sh.reviewNote}
                                            value={session.review_note}
                                        />
                                    ) : null}
                                    {sheet.off_roster.length > 0 ? (
                                        <WindowFact
                                            wide
                                            label={sh.offRoster}
                                            value={sheet.off_roster
                                                .map(
                                                    (r) =>
                                                        `${r.full_name} (${recordLabel(r.status)})`,
                                                )
                                                .join('، ')}
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {historyOpen ? (
                <AttendanceWindow
                    wide
                    title={sh.history}
                    onClose={() => setHistoryOpen(false)}
                    sections={[
                        {
                            id: 'att-sheet-history',
                            title: `${sh.history} (${sheet.events.length})`,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <AttendanceTable
                                        caption={sh.history}
                                        storageKey="sheet.history"
                                        rows={sheet.events}
                                        rowKey={(e) => e.id}
                                        emptyText="—"
                                        fit={false}
                                        emptyAsDialog={false}
                                        columns={[
                                            {
                                                id: 'at',
                                                header: sh.when,
                                                kind: 'text',
                                                cell: (e) =>
                                                    dateTime(e.created_at),
                                            },
                                            {
                                                id: 'event',
                                                header: sh.event,
                                                kind: 'name',
                                                cell: (e) =>
                                                    sh.events[e.event_type] ??
                                                    e.event_type,
                                            },
                                            {
                                                id: 'actor',
                                                header: sh.by,
                                                kind: 'text',
                                                cell: (e) =>
                                                    e.actor_name ?? '—',
                                            },
                                            {
                                                id: 'note',
                                                header: sh.note,
                                                kind: 'text',
                                                cell: (e) =>
                                                    e.note === 'on_behalf'
                                                        ? sh.onBehalf
                                                        : (e.note ?? '—'),
                                            },
                                            {
                                                id: 'version',
                                                header: sh.version,
                                                kind: 'num',
                                                cell: (e) => (
                                                    <span dir="ltr">
                                                        {e.version}
                                                    </span>
                                                ),
                                            },
                                        ]}
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {recordFor !== null ? (
                <AttendanceWindow
                    title={`${a.reports.types.student_detail} — ${recordFor.full_name}`}
                    columns={2}
                    wide
                    onClose={() => setRecordFor(null)}
                    sections={[
                        {
                            id: 'att-record-totals',
                            title:
                                student_record?.all_subjects === false
                                    ? `${sh.summary} — ${session.subject_name ?? ''}`
                                    : sh.summary,
                            content:
                                recordRows === null ? (
                                    <WindowFact
                                        wide
                                        label={sh.summary}
                                        value={t().dialog.working}
                                    />
                                ) : (
                                    <>
                                        <WindowFact
                                            label={sh.code}
                                            value={
                                                <span dir="ltr">
                                                    {recordFor.student_code}
                                                </span>
                                            }
                                        />
                                        <WindowFact
                                            label={sh.recorded}
                                            value={
                                                <span dir="ltr">
                                                    {recordRows.length}
                                                </span>
                                            }
                                        />
                                        {RECORD_STATUSES.map((st) => (
                                            <WindowFact
                                                key={st.value}
                                                label={recordLabel(st.value)}
                                                value={
                                                    <span dir="ltr">
                                                        {recordTotals[
                                                            st.value
                                                        ] ?? 0}
                                                    </span>
                                                }
                                            />
                                        ))}
                                    </>
                                ),
                        },
                        {
                            id: 'att-record-rows',
                            title: a.reports.types.student_detail,
                            plain: true,
                            content: (
                                <div className="sis-branches-field--wide">
                                    <AttendanceTable
                                        caption={a.reports.types.student_detail}
                                        storageKey="sheet.student-record"
                                        rows={(recordRows ?? []).map(
                                            (
                                                r,
                                                i,
                                            ): RecordRow & { __i: number } => ({
                                                ...r,
                                                __i: i,
                                            }),
                                        )}
                                        rowKey={(r) => Number(r.__i)}
                                        emptyText={a.reports.empty}
                                        fit={false}
                                        emptyAsDialog={false}
                                        columns={[
                                            {
                                                id: 'date',
                                                header: a.reports.columns.date,
                                                kind: 'text',
                                                cell: (r) => (
                                                    <span dir="ltr">
                                                        {String(r.date ?? '')}
                                                    </span>
                                                ),
                                            },
                                            {
                                                id: 'subject',
                                                header: a.reports.columns
                                                    .subject_name,
                                                kind: 'name',
                                                cell: (r) =>
                                                    String(
                                                        r.subject_name ?? '—',
                                                    ),
                                            },
                                            {
                                                id: 'period',
                                                header: a.reports.columns
                                                    .period_number,
                                                kind: 'num',
                                                cell: (r) =>
                                                    r.period_number ?? '—',
                                            },
                                            {
                                                id: 'status',
                                                header: sh.status,
                                                kind: 'status',
                                                tone: (r) =>
                                                    recordStatusMeta(
                                                        Number(r.status),
                                                    ).tone,
                                                cell: (r) => (
                                                    <StatusLabel
                                                        meta={recordStatusMeta(
                                                            Number(r.status),
                                                        )}
                                                        label={recordLabel(
                                                            Number(r.status),
                                                        )}
                                                    />
                                                ),
                                            },
                                            {
                                                id: 'late',
                                                header: sh.minutesLate,
                                                kind: 'num',
                                                cell: (r) =>
                                                    r.minutes_late ?? '—',
                                            },
                                            {
                                                id: 'sheet',
                                                header: a.reports.columns
                                                    .sheet_status,
                                                kind: 'text',
                                                cell: (r) =>
                                                    sheetLabel(
                                                        Number(r.sheet_status),
                                                    ),
                                            },
                                        ]}
                                    />
                                </div>
                            ),
                        },
                    ]}
                />
            ) : null}

            {review !== null ? (
                <AttendanceWindow
                    title={
                        review.action === 'approve'
                            ? sh.approveTitle
                            : review.action === 'return'
                              ? sh.returnTitle
                              : review.action === 'reject'
                                ? sh.rejectTitle
                                : sh.startReview
                    }
                    columns={2}
                    pending={busy === 'reviewing'}
                    danger={review.action === 'reject'}
                    dirty={review.reason !== ''}
                    confirmLabel={
                        review.action === 'approve'
                            ? sh.approve
                            : review.action === 'return'
                              ? sh.return
                              : review.action === 'reject'
                                ? sh.reject
                                : sh.startReview
                    }
                    confirmDisabled={
                        (review.action === 'return' ||
                            review.action === 'reject') &&
                        review.reason.trim() === ''
                    }
                    onConfirm={runReview}
                    onClose={() => setReview(null)}
                    sections={[
                        {
                            id: 'att-review',
                            title: sh.summary,
                            content: (
                                <>
                                    <WindowFact
                                        wide
                                        label={sh.lesson}
                                        value={`${facts} · ${session.subject_name ?? ''} · ${session.class_name ?? ''} / ${session.section_name ?? ''} · ${session.teacher_name ?? ''}`}
                                    />
                                    <WindowFact
                                        label={sh.expected}
                                        value={
                                            <span dir="ltr">
                                                {sheet.rows.length}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={sh.unmarked}
                                        value={
                                            <span dir="ltr">{counts[0]}</span>
                                        }
                                    />
                                    {review.action === 'approve' ? (
                                        <WindowFact
                                            wide
                                            label={sh.approve}
                                            value={sh.approveConfirm}
                                        />
                                    ) : null}
                                    {review.action !== 'start_review' ? (
                                        <WindowTextArea
                                            label={
                                                review.action === 'approve'
                                                    ? sh.note
                                                    : sh.reason
                                            }
                                            required={
                                                review.action !== 'approve'
                                            }
                                            value={review.reason}
                                            onChange={(reason) =>
                                                setReview({ ...review, reason })
                                            }
                                        />
                                    ) : null}
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            {correction !== null ? (
                <AttendanceWindow
                    title={sh.correctTitle}
                    columns={2}
                    pending={busy === 'reviewing'}
                    dirty={correction.reason !== ''}
                    confirmLabel={sh.correct}
                    confirmDisabled={correction.reason.trim().length < 3}
                    onConfirm={runCorrection}
                    onClose={() => setCorrection(null)}
                    sections={[
                        {
                            id: 'att-correct',
                            title: correction.row.full_name,
                            content: (
                                <>
                                    <WindowFact
                                        label={sh.status}
                                        value={recordLabel(
                                            correction.row.status,
                                        )}
                                    />
                                    <RegistryListField
                                        label={sh.newStatus}
                                        editing
                                        required
                                        value={correction.status}
                                        display={recordLabel(
                                            Number(correction.status),
                                        )}
                                        options={RECORD_STATUSES.map((st) => ({
                                            value: String(st.value),
                                            label: recordLabel(st.value),
                                        }))}
                                        onChange={(status) =>
                                            setCorrection({
                                                ...correction,
                                                status,
                                            })
                                        }
                                    />
                                    <WindowTextArea
                                        label={sh.correctReason}
                                        required
                                        value={correction.reason}
                                        onChange={(reason) =>
                                            setCorrection({
                                                ...correction,
                                                reason,
                                            })
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}
        </AttendancePage>
    );
}
