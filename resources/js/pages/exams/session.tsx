import { router } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleCheckBig,
    ClipboardPen,
    Hash,
    LogOut,
    RotateCcw,
    UserCheck,
    UserX,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AttendanceTable,
    AttendanceWindow,
    useAttendanceSelection,
    type AttendanceColumn,
} from '@/components/attendance/attendance-chrome';
import {
    ExamisPage,
    useExamisEditRibbon,
    useExamisHomeFilters,
    useExamisRequest,
} from '@/components/examis/examis-chrome';
import { RegistryTextField } from '@/components/organization/registry-sheet';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type {
    PageRibbonCommand,
    PageRibbonGroup,
} from '@/components/sis/page-ribbon-context';
import { statusChipLabel } from '@/components/sis/status-chip';
import { t } from '@/i18n';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Session = {
    id: number;
    exam_id: number;
    exam_name: string;
    academic_year_id: number;
    subject_name: string;
    session_date: string;
    start_time: string;
    end_time: string;
    status: number;
};

type Seat = {
    exam_enrollment_id: number;
    seat_number: string | null;
    status: number;
    student_code: string;
    student_name: string;
    class_name: string | null;
    section_name: string | null;
    has_current_grade: boolean;
};

type PageProps = {
    session: Session;
    seats: { data: Seat[] };
    can: {
        update: boolean;
        present: boolean;
        cancel: boolean;
        reopen: boolean;
    };
};

/** exams.exam_enrollments.status — 1 registered · 2 confirmed · 3 present · 4 absent · 5 withdrawn. */
const REGISTERED = 1;
const CONFIRMED = 2;
const PRESENT = 3;
const ABSENT = 4;
const WITHDRAWN = 5;
/** exams.exam_sessions.status — 2 in progress · 4 cancelled. */
const SESSION_IN_PROGRESS = 2;
const SESSION_CANCELLED = 4;
const ACTIVE = [REGISTERED, CONFIRMED, PRESENT];

const SEAT_STATUSES: Array<{
    status: number;
    icon: LucideIcon;
    tone: NonNullable<PageRibbonCommand['iconTone']>;
    cell: number;
}> = [
    { status: REGISTERED, icon: Hash, tone: 'steel', cell: 0 },
    { status: CONFIRMED, icon: CheckCircle2, tone: 'sky', cell: 3 },
    { status: PRESENT, icon: UserCheck, tone: 'ok', cell: 1 },
    { status: ABSENT, icon: UserX, tone: 'warning', cell: 5 },
    { status: WITHDRAWN, icon: LogOut, tone: 'danger', cell: 4 },
];

type Action = 'confirm' | 'present' | 'absent' | 'withdraw' | 'reopen';

export default function ExamSessionRoster(props: PageProps) {
    const i18n = t();
    const r = i18n.exams.roster;
    const { session } = props;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: session.exam_name, href: `/exams/${session.exam_id}` },
        {
            title: r.title,
            href: `/exams/${session.exam_id}/sessions/${session.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <RosterBody {...props} />
        </AppLayout>
    );
}

/** «كشف المرشحين» — the seats of one session; each command mirrors a transition the server allows (and re-checks). */
function RosterBody({ session, seats, can }: PageProps) {
    const i18n = t();
    const r = i18n.exams.roster;
    const request = useExamisRequest();
    const [statusFilter, setStatusFilter] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const [confirming, setConfirming] = useState<{
        seats: Seat[];
        action: 'absent' | 'withdraw';
    } | null>(null);
    const [editing, setEditing] = useState<{
        seat: Seat;
        value: string;
    } | null>(null);
    const base = `/exams/${session.exam_id}/sessions/${session.id}/seats`;
    const live = session.status !== SESSION_CANCELLED;
    const rows = useMemo(
        () =>
            statusFilter === null
                ? seats.data
                : seats.data.filter((seat) => seat.status === statusFilter),
        [seats.data, statusFilter],
    );
    const selection = useAttendanceSelection(
        rows,
        (row) => row.exam_enrollment_id,
        `exam-session-${session.id}-roster`,
    );

    const eligible: Record<Action, (seat: Seat) => boolean> = {
        confirm: (seat) => can.update && seat.status === REGISTERED,
        present: (seat) =>
            can.present &&
            seat.status === CONFIRMED &&
            session.status === SESSION_IN_PROGRESS,
        absent: (seat) => can.update && ACTIVE.includes(seat.status),
        withdraw: (seat) =>
            can.cancel &&
            ACTIVE.includes(seat.status) &&
            !seat.has_current_grade,
        reopen: (seat) => can.reopen && seat.status === WITHDRAWN,
    };
    const seatNumberEditable = (seat: Seat) =>
        can.update &&
        ![PRESENT, ABSENT, WITHDRAWN].includes(seat.status) &&
        !seat.has_current_grade;

    /** One request per seat (its own key): a refused seat never hides the others. */
    const send = async (targets: Seat[], action: Action) => {
        const route: Record<
            Action,
            (id: number) => [string, Record<string, number>]
        > = {
            confirm: (id) => [`${base}/${id}`, { status: CONFIRMED }],
            absent: (id) => [`${base}/${id}`, { status: ABSENT }],
            present: (id) => [`${base}/${id}/present`, {}],
            withdraw: (id) => [`${base}/${id}/cancel`, {}],
            reopen: (id) => [`${base}/${id}/reopen`, {}],
        };
        setBusy(true);
        for (const seat of targets.filter(eligible[action])) {
            const [url, data] = route[action](seat.exam_enrollment_id);
            await request(url, data, 'exam-roster');
        }
        setBusy(false);
        setConfirming(null);
    };

    const saveSeatNumber = async () => {
        if (editing === null) {
            return;
        }
        setBusy(true);
        const ok = await request(
            `${base}/${editing.seat.exam_enrollment_id}`,
            { seat_number: editing.value },
            'exam-roster-seat',
        );
        setBusy(false);
        if (ok) {
            setEditing(null);
        }
    };

    const ribbon = useMemo((): PageRibbonGroup[] => {
        const targets = selection.targets;
        const some = (action: Action) =>
            live && !busy && targets.some(eligible[action]);
        const command = (
            action: Action,
            label: string,
            icon: LucideIcon,
            tone: NonNullable<PageRibbonCommand['iconTone']>,
            onSelect: () => void,
        ): PageRibbonCommand => ({
            id: `roster-${action}`,
            label,
            icon,
            iconTone: tone,
            disabled: !some(action),
            title:
                action === 'present' && session.status !== SESSION_IN_PROGRESS
                    ? r.presentNeedsOpenSession
                    : label,
            onSelect,
        });
        const single = targets.length === 1 ? targets[0] : null;

        return [
            {
                id: 'roster-actions',
                label:
                    targets.length > 0
                        ? `${r.attendance} (${targets.length})`
                        : r.attendance,
                commands: [
                    ...(can.update
                        ? [
                              command(
                                  'confirm',
                                  r.confirm,
                                  CircleCheckBig,
                                  'sky',
                                  () => void send(targets, 'confirm'),
                              ),
                          ]
                        : []),
                    ...(can.present
                        ? [
                              command(
                                  'present',
                                  r.present,
                                  UserCheck,
                                  'ok',
                                  () => void send(targets, 'present'),
                              ),
                          ]
                        : []),
                    ...(can.update
                        ? [
                              command(
                                  'absent',
                                  r.absent,
                                  UserX,
                                  'warning',
                                  () =>
                                      setConfirming({
                                          seats: targets.filter(
                                              eligible.absent,
                                          ),
                                          action: 'absent',
                                      }),
                              ),
                          ]
                        : []),
                ],
            },
            {
                id: 'roster-seat',
                label: r.seatNumber,
                commands: [
                    ...(can.cancel
                        ? [
                              command(
                                  'withdraw',
                                  r.withdraw,
                                  LogOut,
                                  'danger',
                                  () =>
                                      setConfirming({
                                          seats: targets.filter(
                                              eligible.withdraw,
                                          ),
                                          action: 'withdraw',
                                      }),
                              ),
                          ]
                        : []),
                    ...(can.reopen
                        ? [
                              command(
                                  'reopen',
                                  r.reopen,
                                  RotateCcw,
                                  'ok',
                                  () => void send(targets, 'reopen'),
                              ),
                          ]
                        : []),
                    ...(can.update
                        ? [
                              {
                                  id: 'roster-edit-seat',
                                  label: r.editSeat,
                                  icon: Hash,
                                  iconTone: 'amber' as const,
                                  tone: 'edit' as const,
                                  disabled:
                                      !live ||
                                      busy ||
                                      single === null ||
                                      !seatNumberEditable(single),
                                  onSelect: () =>
                                      single &&
                                      setEditing({
                                          seat: single,
                                          value: single.seat_number ?? '',
                                      }),
                              },
                          ]
                        : []),
                ],
            },
            {
                id: 'roster-open',
                label: i18n.common.open,
                commands: [
                    {
                        id: 'roster-enter-grades',
                        label: i18n.exams.enterGrades,
                        icon: ClipboardPen,
                        iconTone: 'growth',
                        onSelect: () =>
                            router.visit(
                                `/grades/sheet?academic_year_id=${session.academic_year_id}&session_id=${session.id}`,
                            ),
                    },
                ],
            },
        ];
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selection.targets, busy, can, live]);
    useExamisEditRibbon('exams', ribbon);

    // «الصفحة الرئيسية»: the seat-status filter (the counts are in the facts line above the table).
    useExamisHomeFilters(
        [
            {
                key: 'status',
                type: 'single',
                label: i18n.common.status,
                value: statusFilter === null ? '' : String(statusFilter),
                options: [
                    { value: '', label: `${i18n.common.status}: ${r.all}` },
                    ...SEAT_STATUSES.map((s) => ({
                        value: String(s.status),
                        label: statusChipLabel('examSeat', s.status),
                    })),
                ],
                onChange: (value: string) =>
                    setStatusFilter(value === '' ? null : Number(value)),
            },
        ],
        [statusFilter],
    );

    const placement = (seat: Seat) =>
        [seat.class_name, seat.section_name].filter(Boolean).join(' / ') || '—';
    const tone = (status: number) =>
        SEAT_STATUSES.find((s) => s.status === status)?.cell ?? 0;

    const columns: AttendanceColumn<Seat>[] = [
        {
            id: 'seat',
            header: r.seatNumber,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.seat_number ?? '—'}</span>,
            sort: (row) => row.seat_number ?? '',
        },
        {
            id: 'student',
            header: r.student,
            kind: 'name',
            cell: (row) => row.student_name,
        },
        {
            id: 'code',
            header: r.studentCode,
            kind: 'text',
            cell: (row) => <span dir="ltr">{row.student_code}</span>,
        },
        {
            id: 'placement',
            header: r.placement,
            kind: 'text',
            cell: placement,
        },
        {
            id: 'graded',
            header: r.graded,
            kind: 'text',
            cell: (row) =>
                row.has_current_grade ? i18n.common.yes : i18n.common.no,
        },
        {
            id: 'status',
            header: i18n.common.status,
            kind: 'status',
            tone: (row) => tone(row.status),
            cell: (row) => statusChipLabel('examSeat', row.status),
            sort: (row) => row.status,
        },
    ];

    const count = (status: number) =>
        seats.data.filter((seat) => seat.status === status).length;
    const counts = r.counts
        .replace(':total', String(seats.data.length))
        .replace(':present', String(count(PRESENT)))
        .replace(':absent', String(count(ABSENT)))
        .replace(':withdrawn', String(count(WITHDRAWN)));
    const title = `${r.title} — ${session.subject_name}`;

    return (
        <ExamisPage title={title} windowId="exams.session.roster">
            <dl className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <div className="flex gap-1">
                    <dt className="font-semibold">{r.exam}:</dt>
                    <dd>{session.exam_name}</dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.attendance.date}:</dt>
                    <dd dir="ltr">
                        {session.session_date} · {session.start_time} –{' '}
                        {session.end_time}
                    </dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.common.status}:</dt>
                    <dd>{statusChipLabel('exam', session.status)}</dd>
                </div>
                <div className="flex gap-1">
                    <dt className="font-semibold">{i18n.exams.seat.column}:</dt>
                    <dd role="status" data-roster-counts>
                        {counts}
                    </dd>
                </div>
            </dl>
            <AttendanceTable
                caption={r.caption}
                storageKey="exams.roster"
                rows={rows}
                rowKey={(row) => row.exam_enrollment_id}
                columns={columns}
                selection={selection}
                emptyText={r.emptyDesc}
            />

            {editing !== null ? (
                <AttendanceWindow
                    title={`${r.editSeat} — ${editing.seat.student_name}`}
                    pending={busy}
                    confirmLabel={r.saveSeat}
                    onConfirm={() => void saveSeatNumber()}
                    onClose={() => setEditing(null)}
                    sections={[
                        {
                            id: 'roster-seat-number',
                            title: r.seatNumber,
                            content: (
                                <RegistryTextField
                                    label={r.seatNumber}
                                    editing
                                    dir="ltr"
                                    value={editing.value}
                                    onChange={(value) =>
                                        setEditing({
                                            ...editing,
                                            value: value.slice(0, 32),
                                        })
                                    }
                                />
                            ),
                        },
                    ]}
                />
            ) : null}

            <ConfirmDialog
                open={confirming !== null}
                title={
                    confirming?.action === 'absent'
                        ? r.absentConfirmTitle
                        : r.withdrawConfirmTitle
                }
                description={
                    `${(confirming?.seats ?? []).map((seat) => seat.student_name).join('، ')} — ` +
                    (confirming?.action === 'absent'
                        ? r.absentConfirmDesc
                        : r.withdrawConfirmDesc)
                }
                confirmLabel={
                    confirming?.action === 'absent' ? r.absent : r.withdraw
                }
                confirmPending={busy}
                tone="danger"
                onConfirm={() =>
                    confirming && void send(confirming.seats, confirming.action)
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirming(null);
                    }
                }}
            />
        </ExamisPage>
    );
}
