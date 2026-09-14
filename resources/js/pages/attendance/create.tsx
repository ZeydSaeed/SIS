import { Form, Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
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
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.attendance.createTitle}
                    description={i18n.attendance.createDesc}
                    icon={<CalendarCheck className="size-6" aria-hidden />}
                />
                <Link href="/attendance" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
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
                            <OpsFormField
                                label={i18n.attendance.academicYearId}
                                name="academic_year_id"
                                error={errors.academic_year_id}
                            >
                                <OpsTextInput
                                    name="academic_year_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={defaults.academic_year_id ?? undefined}
                                    error={errors.academic_year_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.attendance.sectionId}
                                name="section_id"
                                error={errors.section_id}
                            >
                                <OpsTextInput
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    error={errors.section_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.attendance.subjectId}
                                name="subject_id"
                                error={errors.subject_id}
                            >
                                <OpsTextInput
                                    name="subject_id"
                                    type="number"
                                    min={1}
                                    required
                                    error={errors.subject_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.attendance.sessionDate}
                                name="session_date"
                                error={errors.session_date}
                            >
                                <OpsTextInput
                                    name="session_date"
                                    type="date"
                                    required
                                    defaultValue={defaults.session_date}
                                    error={errors.session_date}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.attendance.teacherId}
                                name="teacher_id"
                                error={errors.teacher_id}
                            >
                                <OpsTextInput
                                    name="teacher_id"
                                    type="number"
                                    min={1}
                                    required
                                    error={errors.teacher_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.attendance.periodIdOptional}
                                name="period_id"
                                error={errors.period_id}
                            >
                                <OpsTextInput
                                    name="period_id"
                                    type="number"
                                    min={1}
                                    error={errors.period_id}
                                />
                            </OpsFormField>
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm" dir="rtl" lang="ar"
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
