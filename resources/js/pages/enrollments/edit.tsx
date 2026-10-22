import { Form, Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import { OpsFormField } from '@/components/sis/ops-form-field';
import { PageHeader } from '@/components/sis/page-header';
import { usePageError } from '@/components/sis/page-error-context';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type Enrollment = {
    id: number;
    student_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    student_full_name: string | null;
};

type PageProps = {
    enrollment: Enrollment;
    filterOptions: EnrollmentFormFilterOptions;
};

export default function EnrollmentEdit({ enrollment, filterOptions }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.enrollments.title, href: '/enrollments' },
        { title: i18n.enrollments.viewTitle, href: `/enrollments/${enrollment.id}` },
        { title: i18n.enrollments.editPlacement, href: `/enrollments/${enrollment.id}/edit` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.enrollments.editTitle} />
            <EnrollmentEditForm enrollment={enrollment} filterOptions={filterOptions} />
        </AppLayout>
    );
}

/** Inside AppLayout: usePageError needs the layout's PageErrorProvider. */
function EnrollmentEditForm({ enrollment, filterOptions }: PageProps) {
    const i18n = t();
    const { showInertiaErrors, showSuccess } = usePageError();
    // Named lists (they used to be raw ids), starting at the enrollment's current class and section. The page's former
    // «specialization id» field is gone: the update action never used it (the specialization follows the department).
    const [classId, setClassId] = useState(String(enrollment.class_id));
    const sections = filterOptions.sections.filter((section) => String(section.class_id) === classId);
    const selectClass = 'sis-ops-hub__link min-h-11 px-3 py-2';

    return (
        <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
            <PageHeader
                title={i18n.enrollments.editTitle}
                description={i18n.enrollments.editDesc}
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
                onSuccess={(page) => {
                    // A domain refusal comes back as a redirect with flash.error (shown by the page's message dialog).
                    if (!(page.props as { flash?: { error?: string | null } }).flash?.error) {
                        showSuccess(i18n.flash.enrollment.updated);
                    }
                }}
                onError={(errors) => showInertiaErrors(errors, i18n.errors.saveFailed)}
            >
                {({ errors, processing }) => (
                    <>
                        <p className="text-sm opacity-80">
                            {i18n.enrollments.student}{' '}
                            <span>{enrollment.student_full_name ?? '—'}</span>
                        </p>
                        <OpsFormField label={i18n.enrollments.className} name="class_id" error={errors.class_id}>
                            <select
                                id="class_id"
                                name="class_id"
                                required
                                value={classId}
                                onChange={(event) => setClassId(event.target.value)}
                                className={selectClass} dir="rtl" lang="ar"
                            >
                                {filterOptions.classes.map((option) => (
                                    <option key={option.id} value={option.id}>{option.name}</option>
                                ))}
                            </select>
                        </OpsFormField>
                        <OpsFormField label={i18n.enrollments.sectionName} name="section_id" error={errors.section_id}>
                            <select
                                key={classId}
                                id="section_id"
                                name="section_id"
                                required
                                defaultValue={String(enrollment.class_id) === classId ? String(enrollment.section_id) : String(sections[0]?.id ?? '')}
                                className={selectClass} dir="rtl" lang="ar"
                            >
                                {sections.map((option) => (
                                    <option key={option.id} value={option.id}>{option.name}</option>
                                ))}
                            </select>
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
    );
}
