import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    StudentList,
    type StudentAuthorization,
    type PreviewPayload,
    type StudentsPayload,
} from '@/components/students/student-list';
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
    };
    authorization: StudentAuthorization;
    preview: PreviewPayload;
};

export default function StudentsIndex({ students, filters, authorization, preview }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [{ title: i18n.students.title, href: '/students' }];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={i18n.students.title} />
            <StudentList
                students={students}
                filters={filters}
                authorization={authorization}
                preview={preview}
            />
        </AppLayout>
    );
}
