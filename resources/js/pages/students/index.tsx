import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import {
    StudentList,
    type StudentAuthorization,
    type PreviewPayload,
    type StudentsPayload,
} from '@/components/students/student-list';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    students: StudentsPayload;
    filters: {
        q: string;
        status: number | null;
        page: number;
        per_page: number;
    };
    authorization: StudentAuthorization;
    preview: PreviewPayload;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Students', href: '/students' }];

export default function StudentsIndex({ students, filters, authorization, preview }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Students" />
            <div className="p-4">
                <StudentList
                    students={students}
                    filters={filters}
                    authorization={authorization}
                    preview={preview}
                />
            </div>
        </AppLayout>
    );
}
