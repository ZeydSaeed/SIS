import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { Button } from '@/components/ui/button';
import {
    StudentDetailsSurface,
    type StudentAuthorization,
    type StudentDetail,
} from '@/components/students/student-details-surface';
import { t } from '@/i18n';
import type { BreadcrumbItem } from '@/types';

type PageProps = {
    student: StudentDetail;
    authorization: StudentAuthorization;
};

export default function StudentsShow({ student, authorization }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.students.title, href: '/students' },
        { title: student.full_name, href: `/students/${student.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={student.full_name} />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/students">
                            <ArrowLeft className="me-2 size-4" aria-hidden="true" />
                            {i18n.common.backToList}
                        </Link>
                    </Button>
                </div>

                <PageHeader title={student.full_name} description={i18n.students.showDesc} />

                <div className="border-border rounded-xl border p-6">
                    <StudentDetailsSurface
                        student={student}
                        authorization={authorization}
                        showProfileLink={false}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
