import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import {
    EnrollmentCreateForm,
    type EnrollmentCreateStudent,
    type EnrollmentFormFilterOptions,
} from '@/components/enrollments/enrollment-record-form';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    defaults: {
        academic_year_id: number | null;
        student_id?: number | null;
        effective_from: string;
    };
    student: EnrollmentCreateStudent | null;
    filterOptions: EnrollmentFormFilterOptions;
};

export default function EnrollmentCreate({ defaults, student, filterOptions }: PageProps) {
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
                <Link
                    href="/enrollments"
                    className="sis-ops-hub__link w-fit px-3 py-2 text-sm"
                    dir="rtl"
                    lang="ar"
                    prefetch
                >
                    {i18n.enrollments.backToEnrollments}
                </Link>
                <EnrollmentCreateForm
                    academicYearId={defaults.academic_year_id}
                    filterOptions={filterOptions}
                    initialStudentId={defaults.student_id ?? null}
                    student={student}
                    showCancel={false}
                />
            </div>
        </AppLayout>
    );
}
