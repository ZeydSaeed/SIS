import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import type { BreadcrumbItem } from '@/types';

type Enrollment = {
    id: number;
    student_id: number;
    school_id: number;
    academic_year_id: number;
    class_id: number;
    section_id: number;
    enrollment_number: string;
    status: number;
    effective_from: string;
    effective_to: string | null;
};

type PageProps = {
    enrollment: Enrollment;
};

export default function EnrollmentShow({ enrollment }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Enrollments', href: '/enrollments' },
        { title: enrollment.enrollment_number, href: `/enrollments/${enrollment.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={enrollment.enrollment_number} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4">
                <PageHeader
                    title={enrollment.enrollment_number}
                    description="Enrollment placement detail for the school year."
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/enrollments" className="sis-ops-hub__link px-3 py-2 text-sm" prefetch>
                        Back to list
                    </Link>
                    <Link
                        href={`/enrollments/${enrollment.id}/edit`}
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                        prefetch
                    >
                        Update placement
                    </Link>
                    <Link
                        href={`/results/show?enrollment_id=${enrollment.id}&academic_year_id=${enrollment.academic_year_id}`}
                        className="sis-ops-hub__link px-3 py-2 text-sm"
                        prefetch
                    >
                        View results
                    </Link>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">Student</dt>
                        <dd>{enrollment.student_id}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Class / Section</dt>
                        <dd>
                            {enrollment.class_id} / {enrollment.section_id}
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Status</dt>
                        <dd>{enrollment.status}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Effective from</dt>
                        <dd>{enrollment.effective_from}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Effective to</dt>
                        <dd>{enrollment.effective_to ?? '—'}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">Academic year</dt>
                        <dd>{enrollment.academic_year_id}</dd>
                    </div>
                </dl>
            </div>
        </AppLayout>
    );
}
