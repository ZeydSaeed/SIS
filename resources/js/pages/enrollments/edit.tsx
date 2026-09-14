import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Enrollment = {
    id: number;
    student_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
};

type PageProps = {
    enrollment: Enrollment;
};

export default function EnrollmentEdit({ enrollment }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.enrollments.title, href: '/enrollments' },
        { title: enrollment.enrollment_number, href: `/enrollments/${enrollment.id}` },
        { title: i18n.enrollments.editPlacement, href: `/enrollments/${enrollment.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${i18n.common.edit} ${enrollment.enrollment_number}`} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={i18n.enrollments.editTitle}
                    description={`${i18n.enrollments.editDesc} (${enrollment.enrollment_number})`}
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <Link
                    href={`/enrollments/${enrollment.id}`}
                    className="sis-ops-hub__link w-fit px-3 py-2 text-sm" dir="rtl" lang="ar"
                    prefetch
                >
                    {i18n.common.backToDetail}
                </Link>
                <Form
                    action={`/enrollments/${enrollment.id}`}
                    method="put"
                    className="grid max-w-xl gap-3"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm opacity-80">
                                {i18n.enrollments.student}{' '}
                                <span dir="ltr">{enrollment.student_id}</span>
                            </p>
                            <label className="flex flex-col gap-1 text-sm">
                                <span>{i18n.enrollments.classId}</span>
                                <input
                                    name="class_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={enrollment.class_id}
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
                                    defaultValue={enrollment.section_id}
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
                                {processing ? i18n.common.saving : i18n.enrollments.updatePlacement}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
