import { Form, Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        session_date: string;
    };
};

export default function AttendanceCreate({ defaults }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.attendance.title, href: '/attendance' },
        { title: i18n.attendance.createSession, href: '/attendance/create' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.attendance.createTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={i18n.attendance.createTitle}
                    description={i18n.attendance.createDesc}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <Link href="/attendance" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" prefetch>
                    {i18n.common.backToList}
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
                                <span>{i18n.attendance.academicYearId}</span>
                                <input
                                    name="academic_year_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={defaults.academic_year_id ?? undefined}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.academic_year_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.academic_year_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.attendance.sectionId}</span>
                                <input
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.section_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.section_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.attendance.subjectId}</span>
                                <input
                                    name="subject_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.subject_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.subject_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.attendance.sessionDate}</span>
                                <input
                                    name="session_date"
                                    type="date"
                                    required
                                    defaultValue={defaults.session_date}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.session_date ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.session_date}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.attendance.teacherId}</span>
                                <input
                                    name="teacher_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
                                />
                                {errors.teacher_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.teacher_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.attendance.periodIdOptional}</span>
                                <input
                                    name="period_id"
                                    type="number"
                                    min={1}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2"
                                    dir="ltr"
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
                                {processing ? i18n.common.saving : i18n.attendance.createSessionSubmit}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
