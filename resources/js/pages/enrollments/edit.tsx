import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { OpsFormField, OpsTextInput } from '@/components/sis/ops-form-field';
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
                            <OpsFormField
                                label={i18n.enrollments.classId}
                                name="class_id"
                                error={errors.class_id}
                            >
                                <OpsTextInput
                                    name="class_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={enrollment.class_id}
                                    error={errors.class_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.enrollments.sectionId}
                                name="section_id"
                                error={errors.section_id}
                            >
                                <OpsTextInput
                                    name="section_id"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={enrollment.section_id}
                                    error={errors.section_id}
                                />
                            </OpsFormField>
                            <OpsFormField
                                label={i18n.enrollments.specializationId}
                                name="specialization_id"
                                error={errors.specialization_id}
                            >
                                <OpsTextInput
                                    name="specialization_id"
                                    type="number"
                                    min={1}
                                    error={errors.specialization_id}
                                />
                            </OpsFormField>
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
