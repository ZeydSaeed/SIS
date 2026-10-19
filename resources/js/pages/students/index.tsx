import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import {
    StudentList,
    studentsOverviewCards,
    type StudentAuthorization,
    type StudentsPayload,
} from '@/components/students/student-list';
import { ModuleOverview, useIsOverview } from '@/components/sis/module-overview';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    students: StudentsPayload;
    filters: {
        q: string;
        status: number | null;
        page: number;
        per_page: number;
        academic_year_id: number | null;
        gender: number | null;
        enrolled: number | null;
        request_kind: number | null;
    };
    authorization: StudentAuthorization;
    enrollmentFilterOptions?: EnrollmentFormFilterOptions;
};

export default function StudentsIndex({
    students,
    filters,
    authorization,
    enrollmentFilterOptions,
}: PageProps) {
    const i18n = t();
    const overview = useIsOverview();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.students.title, href: '/students' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {overview ? (
                <ModuleOverview
                    title={i18n.students.title}
                    listPath="/students"
                    cards={studentsOverviewCards(students, i18n)}
                    allLabel={i18n.overview.allStudents}
                />
            ) : (
                <>
                    <Head title={i18n.students.title} />
                    <StudentList
                        students={students}
                        filters={filters}
                        authorization={authorization}
                        enrollmentFilterOptions={enrollmentFilterOptions}
                    />
                </>
            )}
        </AppLayout>
    );
}
