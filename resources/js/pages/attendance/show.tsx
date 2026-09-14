import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { ConfirmDialog } from '@/components/sis/confirm-dialog';
import { StatusChip } from '@/components/sis/status-chip';
import { t } from '@/i18n';
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

export default function AttendanceShow({ session }: PageProps) {
    const i18n = t();
    const [confirmClose, setConfirmClose] = useState(false);
    const [closing, setClosing] = useState(false);

    const recordStatusLabel = (status: number): string => {
        if (status === 1) {
            return i18n.attendance.present;
        }
        if (status === 2) {
            return i18n.attendance.absent;
        }
        if (status === 3) {
            return i18n.attendance.late;
        }
        return String(status);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.attendance.title, href: '/attendance' },
        {
            title: `${i18n.attendance.sessionHead} ${session.id}`,
            href: `/attendance/${session.id}`,
        },
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
            <Head title={`${i18n.attendance.sessionHead} ${session.id}`} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={`${i18n.attendance.sessionHead} ${session.id}`}
                    description={`${session.session_date} · ${i18n.attendance.section} ${session.section_id} · ${i18n.attendance.subject} ${session.subject_id}`}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/attendance" className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
                        {i18n.common.backToList}
                    </Link>
                    {isOpen ? (
                        <Link
                            href={`/attendance/${session.id}/mark`}
                            className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                            prefetch
                        >
                            {i18n.attendance.mark}
                        </Link>
                    ) : null}
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">{i18n.attendance.date}</dt>
                        <dd>
                            <span dir="ltr">{session.session_date}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.common.status}</dt>
                        <dd>
                            <StatusChip kind="attendance" status={session.status} />
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.attendance.section}</dt>
                        <dd>
                            <span dir="ltr">{session.section_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.attendance.subject}</dt>
                        <dd>
                            <span dir="ltr">{session.subject_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.attendance.teacher}</dt>
                        <dd>
                            <span dir="ltr">{session.teacher_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.attendance.period}</dt>
                        <dd>
                            <span dir="ltr">{session.period_id ?? '—'}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.academicYear}</dt>
                        <dd>
                            <span dir="ltr">{session.academic_year_id}</span>
                        </dd>
                    </div>
                </dl>
                {isOpen ? (
                    <button
                        type="button"
                        className="sis-ops-hub__link w-fit min-h-11 px-4 py-2 text-sm" dir="rtl" lang="ar"
                        onClick={() => setConfirmClose(true)}
                    >
                        {i18n.attendance.closeSession}
                    </button>
                ) : null}
                <ConfirmDialog
                    open={confirmClose}
                    title={i18n.attendance.closeConfirmTitle}
                    description={i18n.attendance.closeConfirmDesc}
                    confirmLabel={i18n.attendance.closeSession}
                    confirmPending={closing}
                    onConfirm={onConfirmClose}
                    onOpenChange={setConfirmClose}
                />
                <section className="flex flex-col gap-2">
                    <h2 className="text-base font-semibold">{i18n.attendance.markedRecords}</h2>
                    {records.length === 0 ? (
                        <p className="text-sm opacity-80">{i18n.attendance.noRecords}</p>
                    ) : (
                        <ul className="divide-y divide-[color:var(--sis-powder-blue)] rounded-md border border-[color:var(--sis-powder-blue)]">
                            {records.map((record) => (
                                <li
                                    key={record.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <span>
                                        {i18n.enrollments.student}{' '}
                                        <span dir="ltr">{record.student_id}</span> ·{' '}
                                        {i18n.attendance.enrollmentId}{' '}
                                        <span dir="ltr">{record.enrollment_id}</span>
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
