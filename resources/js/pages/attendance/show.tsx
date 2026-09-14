import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import type { BreadcrumbItem } from '@/types';

type AttendanceRecord = {
    id: number;
    student_id: number;
    enrollment_id: number;
    status: number;
    notes: string | null;
};

type Session = {
    id: number;
    school_id: number;
    section_id: number;
    subject_id: number;
    academic_year_id: number;
    session_date: string;
    period_id: number | null;
    teacher_id: number;
    status: number;
    records?: AttendanceRecord[];
};

type PageProps = {
    session: Session;
};

const SESSION_OPEN = 1;

function sessionStatusLabel(status: number): string {
    if (status === 1) {
        return 'Open';
    }
    if (status === 2) {
        return 'Closed';
    }
    if (status === 3) {
        return 'Cancelled';
    }
    return String(status);
}

function recordStatusLabel(status: number): string {
    if (status === 1) {
        return 'Present';
    }
    if (status === 2) {
        return 'Absent';
    }
    if (status === 3) {
        return 'Late';
    }
    return String(status);
}

export default function AttendanceShow({ session }: PageProps) {
    const [confirmClose, setConfirmClose] = useState(false);
    const [closing, setClosing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Attendance', href: '/attendance' },
        { title: `Session ${session.id}`, href: `/attendance/${session.id}` },
    ];

    const records = session.records ?? [];
    const isOpen = session.status === SESSION_OPEN;

    const onConfirmClose = () => {
        setClosing(true);
        router.post(
            `/attendance/${session.id}/close`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setClosing(false);
                    setConfirmClose(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Attendance session ${session.id}`} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={`Session ${session.id}`}
                    description={`${session.session_date} · Section ${session.section_id} · Subject ${session.subject_id}`}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/attendance" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Back to list
                    </Link>
                    {isOpen ? (
                        <Link
                            href={`/attendance/${session.id}/mark`}
                            className="sis-ops-hub__link px-3 py-2 text-sm"
                            prefetch
                        >
                            Mark attendance
                        </Link>
                    ) : null}
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">Date</dt>
                        <dd>{session.session_date}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Status</dt>
                        <dd>{sessionStatusLabel(session.status)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Section</dt>
                        <dd>{session.section_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Subject</dt>
                        <dd>{session.subject_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Teacher</dt>
                        <dd>{session.teacher_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Period</dt>
                        <dd>{session.period_id ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Academic year</dt>
                        <dd>{session.academic_year_id}</dd>
                    </div>
                </dl>
                {isOpen ? (
                    <button
                        type="button"
                        className="sis-ops-hub__link w-fit min-h-11 px-4 py-2 text-sm"
                        onClick={() => setConfirmClose(true)}
                    >
                        Close session
                    </button>
                ) : null}
                <ConfirmDialog
                    open={confirmClose}
                    title="Close attendance session?"
                    description="Closing locks further marking on this session. Corrections may still be allowed when policy permits."
                    confirmLabel="Close session"
                    confirmPending={closing}
                    onConfirm={onConfirmClose}
                    onOpenChange={setConfirmClose}
                />
                <section className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold">Marked records</h2>
                    {records.length === 0 ? (
                        <p className="text-sm opacity-80">No attendance records yet.</p>
                    ) : (
                        <ul className="divide-y divide-[color:var(--sis-powder-blue)] rounded-md border border-[color:var(--sis-powder-blue)]">
                            {records.map((record) => (
                                <li
                                    key={record.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <span>
                                        Student {record.student_id} · Enrollment {record.enrollment_id}
                                    </span>
                                    <span className="rounded px-2 py-0.5 bg-[color-mix(in_srgb,var(--sis-powder-blue)_25%,white)]">
                                        {recordStatusLabel(record.status)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
