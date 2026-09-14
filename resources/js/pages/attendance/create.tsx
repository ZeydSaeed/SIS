import { Form, Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        session_date: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Attendance', href: '/attendance' },
    { title: 'Create session', href: '/attendance/create' },
];

export default function AttendanceCreate({ defaults }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create attendance session" />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title="Create attendance session"
                    description="Open a session for section marking. Server validation is authoritative."
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <Link href="/attendance" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    Back to list
                </Link>
                <Form
                    action="/attendance"
                    method="post"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Academic year ID</span>
                                <input
                                    name="academic_year_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={defaults.academic_year_id ?? undefined}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.academic_year_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.academic_year_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Section ID</span>
                                <input
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.section_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.section_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Subject ID</span>
                                <input
                                    name="subject_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.subject_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.subject_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Session date</span>
                                <input
                                    name="session_date"
                                    type="date"
                                    required
                                    defaultValue={defaults.session_date}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.session_date ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.session_date}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Teacher ID</span>
                                <input
                                    name="teacher_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.teacher_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.teacher_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>Period ID (optional)</span>
                                <input
                                    name="period_id"
                                    type="number"
                                    min={1}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                />
                                {errors.period_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.period_id}
                                    </span>
                                ) : null}
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm"
                            >
                                {processing ? 'Saving…' : 'Create session'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
