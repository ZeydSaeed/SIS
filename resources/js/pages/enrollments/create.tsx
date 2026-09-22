import { Head, router } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import {
    EnrollmentCreateForm,
    type EnrollmentCreateStudent,
    type EnrollmentFormFilterOptions,
} from '@/components/enrollments/enrollment-record-form';
import { PageHeader } from '@/components/sis/page-header';
import { SisWorkflowNotice } from '@/components/sis/sis-workflow-notice';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        student_id?: number | null;
        effective_from: string;
        branch_id?: number | null;
        department_id?: number | null;
        specialization_id?: number | null;
        class_id?: number | null;
    };
    student: EnrollmentCreateStudent | null;
    filterOptions: EnrollmentFormFilterOptions;
    needsEnrollment?: boolean;
};

export default function EnrollmentCreate({
    defaults,
    student,
    filterOptions,
    needsEnrollment = false,
}: PageProps) {
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
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                {needsEnrollment && student ? (
                    <SisWorkflowNotice
                        notice={{
                            tone: 'info',
                            title: i18n.workflow.enrollmentRequiredTitle,
                            message: i18n.workflow.enrollmentRequiredMessage,
                            step: 'enrollment.create',
                        }}
                        dismissLabel={i18n.workflow.dismiss}
                    />
                ) : null}
                <EnrollmentCreateForm
                    academicYearId={defaults.academic_year_id}
                    filterOptions={filterOptions}
                    initialStudentId={defaults.student_id ?? null}
                    student={student}
                    initialDefaults={{
                        class_id: defaults.class_id ?? null,
                        branch_id: defaults.branch_id ?? null,
                        department_id: defaults.department_id ?? null,
                        specialization_id: defaults.specialization_id ?? null,
                        effective_from: defaults.effective_from,
                    }}
                    showCancel
                    onCancel={() => router.visit('/enrollments')}
                />
            </div>
        </AppLayout>
    );
}
