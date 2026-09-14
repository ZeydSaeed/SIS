import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        effective_from: string;
    };
};

export default function EnrollmentCreate({ defaults }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.enrollments.title, href: '/enrollments' },
        { title: i18n.enrollments.enrollStudent, href: '/enrollments/create' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.enrollments.createTitle} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.enrollments.createTitle}
                    description={i18n.enrollments.createDesc}
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <Link href="/enrollments" className="sis-ops-hub__link w-fit px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
                    {i18n.common.backToList}
                </Link>
                <Form
                    action="/enrollments"
                    method="post"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.studentId}</span>
                                <input
                                    name="student_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.student_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.student_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.academicYearId}</span>
                                <input
                                    name="academic_year_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={defaults.academic_year_id ?? undefined}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.academic_year_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.academic_year_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.classId}</span>
                                <input
                                    name="class_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.class_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.class_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.sectionId}</span>
                                <input
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.section_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.section_id}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.effectiveFrom}</span>
                                <input
                                    name="effective_from"
                                    type="date"
                                    required
                                    defaultValue={defaults.effective_from}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.effective_from ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.effective_from}
                                    </span>
                                ) : null}
                            </label>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.specializationId}</span>
                                <input
                                    name="specialization_id"
                                    type="number"
                                    min={1}
                                    className="sis-ops-hub__link min-h-11 px-3 py-2" dir="rtl" lang="ar"
                                    dir="ltr"
                                />
                                {errors.specialization_id ? (
                                    <span className="text-sm text-[color:var(--sis-powder-blush)]">
                                        {errors.specialization_id}
                                    </span>
                                ) : null}
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="sis-ops-hub__link min-h-11 px-4 py-2 text-sm" dir="rtl" lang="ar"
                            >
                                {processing ? i18n.common.saving : i18n.enrollments.createSubmit}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
