import { router, usePage } from '@inertiajs/react';
import {
    CheckCheck,
    ClipboardCheck,
    Eye,
    History,
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
    PageNotice,
    WindowFact,
    WindowTextArea,
    useAttendanceEditRibbon,
    useAttendanceHomeFilters,
    useAttendanceSearch,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    RECORD_STATUSES,
    StatusLabel,
    UNMARKED,
    clock,
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
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
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
    abilities: AttendanceAbilities;
};

type Mark = { status: number; minutes_late: number | null; note: string };
type Marks = Record<number, Mark>;
type ReviewAction = 'start_review' | 'approve' | 'return' | 'reject';
type Sync = 'idle' | 'queued' | 'syncing' | 'conflict' | 'stale';

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

const KEYS = [
    '',
    'present',
    'absent',
    'late',
    'excused',
    'left_early',
] as const;

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

function SheetPage({ sheet, abilities }: Props) {
    const a = t().attendanceSystem;
    const sh = a.sheet;
    const c = a.chrome;
    const session = sheet.session;
    const perms = sheet.permissions;
    const page = usePage().props as {
        flash?: {
            error?: string | null;
            attendanceResult?: {
                action?: string;
                session_id?: number;
                version?: number;
                submitted_at?: string;
                saved_at?: string;
            } | null;
        };
    };
    const { showInertiaErrors } = usePageError();

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
    const [checked, setChecked] = useState<number[]>([]);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [busy, setBusy] = useState<
        'idle' | 'saving' | 'submitting' | 'reviewing'
    >('idle');
    const [online, setOnline] = useState(() =>
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const [sync, setSync] = useState<Sync>('idle');
    const [queued, setQueued] = useState<QueuedDraft | null>(null);
    const [conflict, setConflict] = useState(false);
    const [submitOpen, setSubmitOpen] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [review, setReview] = useState<{
        action: ReviewAction;
        reason: string;
    } | null>(null);
    const [bulk, setBulk] = useState<{ status: number; ids: number[] } | null>(
        null,
    );
    const [details, setDetails] = useState<Row | null>(null);
    const [correction, setCorrection] = useState<{
        row: Row;
        status: string;
        reason: string;
    } | null>(null);
    const saveKeyRef = useRef<{ key: string; payload: string } | null>(null);

    // A new server version becomes the baseline.
    useEffect(() => {
        setMarks(initialMarks());
        setUndoStack([]);
        setConflict(false);
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

    useEffect(() => {
        if (page.flash?.error === 'attendance.sheet_version_conflict')
            setConflict(true);
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
            if (
                dirty &&
                sync !== 'queued' &&
                method === 'get' &&
                !window.confirm(sh.leaveConfirm)
            ) {
                event.preventDefault();
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
    }, [dirty, sync, sh.leaveConfirm]);

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
    const setStatus = (ids: number[], status: number) =>
        apply((current) => {
            const next = { ...current };
            ids.forEach(
                (id) =>
                    (next[id] = {
                        ...current[id],
                        status,
                        minutes_late:
                            status === 3
                                ? (current[id]?.minutes_late ?? null)
                                : null,
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

    const selectedRow =
        sheet.rows.find((r) => r.enrollment_id === selectedId) ?? null;
    const targets =
        checked.length > 0 ? checked : selectedId !== null ? [selectedId] : [];
    const result = page.flash?.attendanceResult ?? null;

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

    // ── Ribbon «تحرير» ──────────────────────────────────────────────────────────────
    const editGroups = useMemo((): PageRibbonGroup[] => {
        const groups: PageRibbonGroup[] = [];
        if (perms.can_edit) {
            groups.push(
                {
                    id: 'attendance-sheet-actions',
                    label: sh.title,
                    commands: [
                        {
                            id: 'attendance-sheet-save',
                            label: sh.saveDraft,
                            icon: Save,
                            iconTone: 'sky',
                            tone: 'save',
                            disabled: !dirty || busy !== 'idle',
                            onSelect: () => send('draft'),
                        },
                        {
                            id: 'attendance-sheet-submit',
                            label: sh.submit,
                            icon: Send,
                            iconTone: 'ok',
                            disabled:
                                busy !== 'idle' ||
                                !online ||
                                sheet.rows.length === 0,
                            title: online ? undefined : sh.offline,
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
                    ],
                },
                {
                    id: 'attendance-sheet-mark',
                    label: sh.markSelected,
                    commands: [
                        {
                            id: 'attendance-sheet-all-present',
                            label: sh.allPresent,
                            icon: CheckCheck,
                            iconTone: 'ok',
                            onSelect: () =>
                                setBulk({
                                    status: 1,
                                    ids: sheet.rows.map((r) => r.enrollment_id),
                                }),
                        },
                        ...allowedStatuses.map((st) => ({
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
                                targets.length > 1
                                    ? setBulk({
                                          status: st.value,
                                          ids: targets,
                                      })
                                    : setStatus(targets, st.value),
                        })),
                    ],
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
            id: 'attendance-sheet-student',
            label: sh.student,
            commands: [
                {
                    id: 'attendance-sheet-details',
                    label: sh.details,
                    icon: Eye,
                    iconTone: 'sky',
                    disabled: selectedRow === null,
                    title: selectedRow === null ? c.needsSelection : undefined,
                    onSelect: () => selectedRow && setDetails(selectedRow),
                },
                ...(perms.can_correct && !perms.can_edit
                    ? [
                          {
                              id: 'attendance-sheet-correct',
                              label: sh.correct,
                              icon: ShieldCheck,
                              iconTone: 'authority' as const,
                              disabled: selectedRow === null,
                              onSelect: () =>
                                  selectedRow && openCorrection(selectedRow),
                          },
                      ]
                    : []),
                ...(abilities.view_reports
                    ? [
                          {
                              id: 'attendance-sheet-history',
                              label: a.reports.types.student_detail,
                              icon: History,
                              iconTone: 'education' as const,
                              disabled: selectedRow === null,
                              onSelect: () =>
                                  selectedRow &&
                                  router.visit(
                                      `/attendance/reports?type=student_detail&student_id=${selectedRow.student_id}`,
                                  ),
                          },
                      ]
                    : []),
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
        undoStack.length,
        targets.length,
        selectedRow,
        counts,
        statusFilter,
        allowedStatuses.length,
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
                `${r.full_name}${sameMark(marks[r.enrollment_id], baseline[r.enrollment_id]) ? '' : ` • ${sh.unsaved}`}`,
        },
        {
            id: 'status',
            header: sh.status,
            kind: 'status',
            tone: (r) =>
                recordStatusMeta(marks[r.enrollment_id]?.status ?? 0).tone,
            cell: (r) => (
                <StatusLabel
                    meta={recordStatusMeta(marks[r.enrollment_id]?.status ?? 0)}
                    label={recordLabel(marks[r.enrollment_id]?.status ?? 0)}
                />
            ),
        },
        ...(perms.can_edit
            ? [
                  {
                      id: 'mark',
                      header: sh.markSelected,
                      kind: 'plain' as const,
                      cell: (r: Row) => (
                          <span
                              role="radiogroup"
                              aria-label={`${sh.status}: ${r.full_name}`}
                              className="inline-flex gap-1"
                              onClick={(event) => event.stopPropagation()}
                              onDoubleClick={(event) => event.stopPropagation()}
                          >
                              {allowedStatuses.map((st) => {
                                  const Icon = st.icon;
                                  const active =
                                      (marks[r.enrollment_id]?.status ?? 0) ===
                                      st.value;

                                  return (
                                      <Button
                                          key={st.value}
                                          type="button"
                                          size="sm"
                                          role="radio"
                                          aria-checked={active}
                                          variant={
                                              active ? 'default' : 'outline'
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
                                      </Button>
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
            kind: 'plain',
            cell: (r) =>
                perms.can_edit && marks[r.enrollment_id]?.status === 3 ? (
                    <input
                        type="number"
                        min={0}
                        max={600}
                        dir="ltr"
                        aria-label={`${sh.minutesLate}: ${r.full_name}`}
                        className="sis-admission-sheet__control w-20"
                        value={marks[r.enrollment_id]?.minutes_late ?? ''}
                        onClick={(event) => event.stopPropagation()}
                        onChange={(event) =>
                            apply((cur) => ({
                                ...cur,
                                [r.enrollment_id]: {
                                    ...cur[r.enrollment_id],
                                    minutes_late:
                                        event.target.value === ''
                                            ? null
                                            : Math.max(
                                                  0,
                                                  Number(event.target.value),
                                              ),
                                },
                            }))
                        }
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
            kind: 'text',
            cell: (r) => (marks[r.enrollment_id]?.note ?? '').trim() || '—',
        },
        {
            id: 'updated',
            header: sh.lastChange,
            kind: 'text',
            cell: (r) => dateTime(r.updated_at),
        },
    ];

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
            id: 'details',
            label: sh.details,
            icon: Eye,
            separator: items.length > 0,
            onSelect: () => setDetails(row),
        });
        if (perms.can_correct && !perms.can_edit)
            items.push({
                id: 'correct',
                label: sh.correct,
                icon: ShieldCheck,
                onSelect: () => openCorrection(row),
            });
        if (abilities.view_reports)
            items.push({
                id: 'history',
                label: a.reports.types.student_detail,
                icon: History,
                onSelect: () =>
                    router.visit(
                        `/attendance/reports?type=student_detail&student_id=${row.student_id}`,
                    ),
            });

        return items;
    };

    const syncText =
        sync === 'queued'
            ? sh.savedOnDevice
            : sync === 'syncing'
              ? sh.retrying
              : !online
                ? sh.offline
                : dirty
                  ? `${sh.unsaved} (${changed.length})`
                  : result?.action === 'draft'
                    ? sh.savedAt
                          .replace(':t', clock(result.saved_at ?? ''))
                          .replace(
                              ':v',
                              String(result.version ?? session.version),
                          )
                    : sh.upToDate;

    const facts = `${dayOfWeekLabel(new Date(`${session.session_date}T12:00:00Z`).getUTCDay() + 1)} ${session.session_date} · ${sh.lesson} ${session.period_number ?? '—'} (${session.start_time ?? '—'}–${session.end_time ?? '—'}) · ${session.subject_name ?? ''} · ${session.lesson_type ? a.lessonType[session.lesson_type] : ''} · ${session.class_name ?? ''} / ${session.section_name ?? ''}${session.group_name ? ` · ${session.group_name}` : ''}${session.room_name ? ` · ${session.room_name}` : ''} · ${session.teacher_name ?? ''}`;

    return (
        <AttendancePage
            title={`${sh.title} ${session.id}`}
            notices={
                <>
                    <PageNotice>{`${sh.sheetNo} ${session.id} · ${sheetLabel(session.sheet_status)}${session.origin === 3 ? ` · ${c.origins[3]}` : ''} — ${facts}`}</PageNotice>
                    <PageNotice>
                        {`${sh.expected} ${sheet.rows.length} · ${sh.present} ${counts[1]} · ${sh.absent} ${counts[2]} · ${sh.late} ${counts[3]} · ${sh.excused} ${counts[4]} · ${sh.leftEarly} ${counts[5]} · ${sh.unmarked} ${counts[0]}`}
                        {perms.can_edit ? ` — ${syncText}` : ''}
                    </PageNotice>
                    {session.review_note && session.sheet_status !== 1 ? (
                        <PageNotice>{`${sh.reviewNote}: ${session.review_note}`}</PageNotice>
                    ) : null}
                    {session.submitted_at ? (
                        <PageNotice>{`${sh.submittedAt}: ${dateTime(session.submitted_at)}${session.submitted_late ? ` · ${sh.lateSubmission}` : ''}${session.reviewed_at ? ` · ${sh.reviewedAt}: ${dateTime(session.reviewed_at)} ${session.reviewed_by_name ?? ''}` : ''}`}</PageNotice>
                    ) : null}
                    {perms.acting_on_behalf ? (
                        <PageNotice>{sh.onBehalf}</PageNotice>
                    ) : null}
                    {!perms.can_edit ? (
                        <PageNotice>{sh.readOnly}</PageNotice>
                    ) : null}
                    {perms.can_edit && sheet.window !== 'open' ? (
                        <PageNotice>{a.window[sheet.window]}</PageNotice>
                    ) : null}
                    {perms.can_edit && counts[0] > 0 ? (
                        <PageNotice>{sh.unmarkedHint}</PageNotice>
                    ) : null}
                    {result?.action === 'submit' ? (
                        <PageNotice>
                            {sh.submittedResult
                                .replace(
                                    ':id',
                                    String(result.session_id ?? session.id),
                                )
                                .replace(
                                    ':t',
                                    clock(result.submitted_at ?? ''),
                                )}
                        </PageNotice>
                    ) : null}
                    {conflict || sync === 'conflict' || sync === 'stale' ? (
                        <p
                            className="sis-branches-page__notice flex flex-wrap items-center gap-2"
                            role="alert"
                        >
                            {sync === 'stale' || sync === 'conflict'
                                ? sh.queuedConflict
                                : sh.conflict}
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => router.reload()}
                            >
                                {sh.reload}
                            </Button>
                            {queued !== null ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        void removeQueued(session.id).then(
                                            () => {
                                                setQueued(null);
                                                setSync('idle');
                                            },
                                        )
                                    }
                                >
                                    {sh.discardLocal}
                                </Button>
                            ) : null}
                        </p>
                    ) : null}
                    {sheet.rows.length === 0 ? (
                        <PageNotice>{sh.noRoster}</PageNotice>
                    ) : null}
                </>
            }
        >
            <AttendanceTable
                caption={sh.title}
                rows={visibleRows}
                rowKey={(r) => r.enrollment_id}
                columns={columns}
                emptyText={sh.noMatch}
                selectable={perms.can_edit ? () => true : undefined}
                checkedIds={checked}
                selectedId={selectedId}
                onToggle={(id) => setChecked((cur) => toggleIn(cur, id))}
                onToggleAll={setChecked}
                onSelect={(r) => setSelectedId(r.enrollment_id)}
                onOpen={setDetails}
                menuItems={menuItems}
                menuLabel={sh.actionsFor}
            />

            {perms.can_edit ? (
                <div className="sis-admission-sheet__actions">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!dirty || busy !== 'idle'}
                        onClick={() => send('draft')}
                    >
                        <Save aria-hidden />
                        {busy === 'saving' ? sh.saving : sh.saveDraft}
                    </Button>
                    <Button
                        type="button"
                        disabled={
                            busy !== 'idle' ||
                            !online ||
                            sheet.rows.length === 0
                        }
                        onClick={() => setSubmitOpen(true)}
                    >
                        <Send aria-hidden />
                        {busy === 'submitting' ? sh.submitting : sh.submit}
                    </Button>
                </div>
            ) : null}

            {sheet.off_roster.length > 0 ? (
                <PageNotice>{`${sh.offRoster}: ${sheet.off_roster.map((r) => `${r.full_name} (${recordLabel(r.status)})`).join('، ')}`}</PageNotice>
            ) : null}

            <p className="sis-branches-page__notice mt-3">{sh.history}</p>
            <AttendanceTable
                caption={sh.history}
                rows={sheet.events}
                rowKey={(e) => e.id}
                emptyText="—"
                columns={[
                    {
                        id: 'at',
                        header: sh.when,
                        kind: 'text',
                        cell: (e) => dateTime(e.created_at),
                    },
                    {
                        id: 'event',
                        header: sh.event,
                        kind: 'name',
                        cell: (e) => sh.events[e.event_type] ?? e.event_type,
                    },
                    {
                        id: 'actor',
                        header: sh.by,
                        kind: 'text',
                        cell: (e) => e.actor_name ?? '—',
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
                        cell: (e) => <span dir="ltr">{e.version}</span>,
                    },
                ]}
            />

            {submitOpen ? (
                <AttendanceWindow
                    title={sh.reviewTitle}
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
                                    {KEYS.map((key, value) =>
                                        value === 0 ? null : (
                                            <WindowFact
                                                key={key}
                                                label={recordLabel(value)}
                                                value={
                                                    <span dir="ltr">
                                                        {counts[value]}
                                                    </span>
                                                }
                                            />
                                        ),
                                    )}
                                    <WindowFact
                                        label={sh.unmarked}
                                        value={
                                            <span dir="ltr">{counts[0]}</span>
                                        }
                                    />
                                    <WindowFact
                                        wide
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
                                <label className="sis-admission-sheet__field sis-branches-field--wide flex-row items-center gap-2">
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
                                        value={facts}
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

            {details !== null ? (
                <AttendanceWindow
                    title={details.full_name}
                    confirmLabel={
                        perms.can_edit ? t().dialog.confirm : undefined
                    }
                    onConfirm={() => setDetails(null)}
                    onClose={() => setDetails(null)}
                    sections={[
                        {
                            id: 'att-student',
                            title: sh.details,
                            content: (
                                <>
                                    <WindowFact
                                        label={sh.code}
                                        value={
                                            <span dir="ltr">
                                                {details.student_code}
                                            </span>
                                        }
                                    />
                                    <WindowFact
                                        label={sh.status}
                                        value={recordLabel(
                                            marks[details.enrollment_id]
                                                ?.status ?? 0,
                                        )}
                                    />
                                    <WindowFact
                                        label={sh.lastChange}
                                        value={dateTime(details.updated_at)}
                                    />
                                    {perms.can_edit &&
                                    marks[details.enrollment_id]?.status ===
                                        3 ? (
                                        <RegistryTextField
                                            label={sh.minutesLate}
                                            editing
                                            type="number"
                                            dir="ltr"
                                            value={String(
                                                marks[details.enrollment_id]
                                                    ?.minutes_late ?? '',
                                            )}
                                            onChange={(value) =>
                                                apply((cur) => ({
                                                    ...cur,
                                                    [details.enrollment_id]: {
                                                        ...cur[
                                                            details
                                                                .enrollment_id
                                                        ],
                                                        minutes_late:
                                                            value === ''
                                                                ? null
                                                                : Math.max(
                                                                      0,
                                                                      Number(
                                                                          value,
                                                                      ),
                                                                  ),
                                                    },
                                                }))
                                            }
                                        />
                                    ) : null}
                                    <WindowTextArea
                                        label={sh.note}
                                        editing={perms.can_edit}
                                        value={
                                            marks[details.enrollment_id]
                                                ?.note ?? ''
                                        }
                                        onChange={(note) =>
                                            apply((cur) => ({
                                                ...cur,
                                                [details.enrollment_id]: {
                                                    ...cur[
                                                        details.enrollment_id
                                                    ],
                                                    note,
                                                },
                                            }))
                                        }
                                    />
                                </>
                            ),
                        },
                    ]}
                />
            ) : null}

            <ConfirmDialog
                open={bulk !== null}
                title={sh.markSelected}
                description={
                    bulk === null
                        ? ''
                        : sh.bulkConfirm
                              .replace(':n', String(bulk.ids.length))
                              .replace(':status', recordLabel(bulk.status))
                }
                confirmLabel={bulk === null ? '' : recordLabel(bulk.status)}
                onConfirm={() => {
                    if (bulk !== null) setStatus(bulk.ids, bulk.status);
                    setBulk(null);
                    setChecked([]);
                }}
                onOpenChange={(open) => !open && setBulk(null)}
            />
        </AttendancePage>
    );
}
