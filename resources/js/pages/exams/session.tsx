import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { DataTable, type DataTableColumn } from '@/components/sis/data-table';
import { PageHeader } from '@/components/sis/page-header';
import { StatusChip } from '@/components/sis/status-chip';
import { t } from '@/i18n';
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
    can: { update: boolean; present: boolean; cancel: boolean; reopen: boolean };
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

type Action = 'confirm' | 'present' | 'absent' | 'withdraw' | 'reopen';

function newKey(): string {
    return `exam-roster-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

/** «كشف المرشحين» — the seats of one session; each button mirrors a transition the server allows (and re-checks). */
export default function ExamSessionRoster({ session, seats, can }: PageProps) {
    const i18n = t();
    const r = i18n.exams.roster;
    const [pending, setPending] = useState<number | null>(null);
    const [confirming, setConfirming] = useState<{ seat: Seat; action: 'absent' | 'withdraw' } | null>(null);
    const [editing, setEditing] = useState<{ id: number; value: string } | null>(null);
    const base = `/exams/${session.exam_id}/sessions/${session.id}/seats`;
    const live = session.status !== SESSION_CANCELLED;

    const send = (seat: Seat, action: Action) => {
        const target: Record<Action, [string, Record<string, number>]> = {
            confirm: [`${base}/${seat.exam_enrollment_id}`, { status: CONFIRMED }],
            absent: [`${base}/${seat.exam_enrollment_id}`, { status: ABSENT }],
            present: [`${base}/${seat.exam_enrollment_id}/present`, {}],
            withdraw: [`${base}/${seat.exam_enrollment_id}/cancel`, {}],
            reopen: [`${base}/${seat.exam_enrollment_id}/reopen`, {}],
        };
        const [url, data] = target[action];
        setPending(seat.exam_enrollment_id);
        router.post(url, data, {
            headers: { 'X-Idempotency-Key': newKey() },
            preserveScroll: true,
            onFinish: () => {
                setPending(null);
                setConfirming(null);
            },
        });
    };

    const saveSeatNumber = (seat: Seat) => {
        if (!editing) {
            return;
        }
        setPending(seat.exam_enrollment_id);
        router.post(
            `${base}/${seat.exam_enrollment_id}`,
            { seat_number: editing.value },
            {
                headers: { 'X-Idempotency-Key': newKey() },
                preserveScroll: true,
                onSuccess: () => setEditing(null),
                onFinish: () => setPending(null),
            },
        );
    };

    const seatNumberEditable = (seat: Seat) =>
        can.update && live && seat.status !== PRESENT && seat.status !== ABSENT && seat.status !== WITHDRAWN && !seat.has_current_grade;

    const actions = (seat: Seat) => {
        const busy = pending !== null;
        const button = (label: string, onClick: () => void, title?: string) => (
            <button type="button" className="underline disabled:opacity-50" disabled={busy} title={title} onClick={onClick}>
                {label}
            </button>
        );

        if (!live) {
            return null;
        }

        return (
            <span className="flex flex-wrap gap-3" data-seat-actions={seat.exam_enrollment_id}>
                {can.update && seat.status === REGISTERED ? button(r.confirm, () => send(seat, 'confirm')) : null}
                {can.present && seat.status === CONFIRMED
                    ? session.status === SESSION_IN_PROGRESS
                        ? button(r.present, () => send(seat, 'present'))
                        : <span className="opacity-70">{r.presentNeedsOpenSession}</span>
                    : null}
                {can.update && [REGISTERED, CONFIRMED, PRESENT].includes(seat.status)
                    ? button(r.absent, () => setConfirming({ seat, action: 'absent' }))
                    : null}
                {can.cancel && [REGISTERED, CONFIRMED, PRESENT].includes(seat.status) && !seat.has_current_grade
                    ? button(r.withdraw, () => setConfirming({ seat, action: 'withdraw' }))
                    : null}
                {can.reopen && seat.status === WITHDRAWN ? button(r.reopen, () => send(seat, 'reopen')) : null}
            </span>
        );
    };

    const seatCell = (seat: Seat) => {
        if (editing?.id === seat.exam_enrollment_id) {
            return (
                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        saveSeatNumber(seat);
                    }}
                >
                    <input
                        aria-label={r.seatNumber}
                        className="sis-ops-hub__link min-h-11 w-24 px-2 py-1"
                        dir="ltr"
                        maxLength={32}
                        value={editing.value}
                        onChange={(e) => setEditing({ id: seat.exam_enrollment_id, value: e.target.value })}
                    />
                    <button type="submit" className="underline disabled:opacity-50" disabled={pending !== null}>
                        {r.saveSeat}
                    </button>
                    <button type="button" className="underline" onClick={() => setEditing(null)}>
                        {r.cancel}
                    </button>
                </form>
            );
        }

        return (
            <span className="flex flex-wrap items-center gap-2">
                <span dir="ltr">{seat.seat_number ?? '—'}</span>
                {seatNumberEditable(seat) ? (
                    <button
                        type="button"
                        className="underline"
                        onClick={() => setEditing({ id: seat.exam_enrollment_id, value: seat.seat_number ?? '' })}
                    >
                        {r.editSeat}
                    </button>
                ) : null}
            </span>
        );
    };

    const placement = (seat: Seat) => [seat.class_name, seat.section_name].filter(Boolean).join(' / ') || '—';

    const columns: DataTableColumn<Seat>[] = [
        { id: 'seat_number', header: r.seatNumber, cell: seatCell, sort: (row) => row.seat_number ?? '' },
        { id: 'student_name', header: r.student, cell: (row) => row.student_name },
        { id: 'student_code', header: r.studentCode, cell: (row) => <span dir="ltr">{row.student_code}</span>, hideOnMobile: true },
        { id: 'placement', header: r.placement, cell: placement, hideOnMobile: true },
        {
            id: 'status',
            header: i18n.common.status,
            cell: (row) => (
                <span className="flex flex-wrap items-center gap-2">
                    <StatusChip kind="examSeat" status={row.status} />
                    {row.has_current_grade ? <span className="text-xs opacity-70">{r.graded}</span> : null}
                </span>
            ),
            sort: (row) => row.status,
        },
        { id: 'actions', header: r.actions, cell: actions, sort: false },
    ];

    const count = (status: number) => seats.data.filter((seat) => seat.status === status).length;
    const counts = r.counts
        .replace(':total', String(seats.data.length))
        .replace(':present', String(count(PRESENT)))
        .replace(':absent', String(count(ABSENT)))
        .replace(':withdrawn', String(count(WITHDRAWN)));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.exams.title, href: '/exams' },
        { title: session.exam_name, href: `/exams/${session.exam_id}` },
        { title: r.title, href: `/exams/${session.exam_id}/sessions/${session.id}` },
    ];
    const title = `${r.title} — ${session.subject_name}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar" data-window-id="exams.session.roster">
                <PageHeader title={title} description={r.description} icon={<ClipboardList className="size-6" aria-hidden />} />
                <div className="flex flex-wrap gap-2">
                    <Link href={`/exams/${session.exam_id}`} className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        {r.backToExam}
                    </Link>
                    <a
                        href={`/grades/sheet?academic_year_id=${session.academic_year_id}&session_id=${session.id}`}
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                    >
                        {i18n.exams.enterGrades}
                    </a>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">{r.exam}</dt>
                        <dd>{session.exam_name}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.attendance.date}</dt>
                        <dd>
                            <span dir="ltr">
                                {session.session_date} · {session.start_time} – {session.end_time}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.common.status}</dt>
                        <dd>
                            <StatusChip kind="exam" status={session.status} />
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.exams.seat.column}</dt>
                        <dd role="status" data-roster-counts>
                            {counts}
                        </dd>
                    </div>
                </dl>
                <ConfirmDialog
                    open={confirming !== null}
                    title={confirming?.action === 'absent' ? r.absentConfirmTitle : r.withdrawConfirmTitle}
                    description={
                        `${confirming?.seat.student_name ?? ''} — ` +
                        (confirming?.action === 'absent' ? r.absentConfirmDesc : r.withdrawConfirmDesc)
                    }
                    confirmLabel={confirming?.action === 'absent' ? r.absent : r.withdraw}
                    confirmPending={pending !== null}
                    tone="danger"
                    onConfirm={() => {
                        if (confirming) {
                            send(confirming.seat, confirming.action);
                        }
                    }}
                    onOpenChange={(open) => {
                        if (!open) {
                            setConfirming(null);
                        }
                    }}
                />
                <DataTable
                    columns={columns}
                    rows={seats.data}
                    rowKey={(row) => row.exam_enrollment_id}
                    emptyTitle={r.emptyTitle}
                    emptyDescription={r.emptyDesc}
                    caption={r.caption}
                    mobileCard={(row) => (
                        <div className="rounded-md border border-[color:var(--sis-powder-blue)] bg-[color-mix(in_srgb,var(--sis-powder-petal)_40%,white)] p-3">
                            <div className="font-semibold">{row.student_name}</div>
                            <div className="text-sm opacity-80">
                                {r.seatNumber}: <span dir="ltr">{row.seat_number ?? '—'}</span> · {placement(row)}
                            </div>
                            <div className="mt-1">
                                <StatusChip kind="examSeat" status={row.status} />
                            </div>
                            <div className="mt-2 text-sm">{actions(row)}</div>
                        </div>
                    )}
                />
            </div>
        </AppLayout>
    );
}
