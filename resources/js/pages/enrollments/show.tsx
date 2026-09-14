import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/components/sis/page-header';
import { t } from '@/i18n';
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
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.enrollments.title, href: '/enrollments' },
        { title: enrollment.enrollment_number, href: `/enrollments/${enrollment.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={enrollment.enrollment_number} />
            <div className="sis-ops-hub flex flex-col gap-4 p-4" dir="rtl" lang="ar">
                <PageHeader
                    title={enrollment.enrollment_number}
                    description={i18n.enrollments.showDesc}
                    icon={<ClipboardList className="size-6" aria-hidden />}
                />
                <div className="flex flex-wrap gap-2">
                    <Link href="/enrollments" className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar" prefetch>
                        {i18n.common.backToList}
                    </Link>
                    <Link
                        href={`/enrollments/${enrollment.id}/edit`}
                        className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                        prefetch
                    >
                        {i18n.enrollments.updatePlacement}
                    </Link>
                    <Link
                        href={`/results/show?enrollment_id=${enrollment.id}&academic_year_id=${enrollment.academic_year_id}`}
                        className="sis-ops-hub__link px-3 py-2 text-sm" dir="rtl" lang="ar"
                        prefetch
                    >
                        {i18n.enrollments.viewResults}
                    </Link>
                </div>
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.student}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.student_id}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.classSection}</dt>
                        <dd>
                            <span dir="ltr">
                                {enrollment.class_id} / {enrollment.section_id}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.common.status}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.status}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.effectiveFrom}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.effective_from}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.effectiveTo}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.effective_to ?? '—'}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.academicYear}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.academic_year_id}</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </AppLayout>
    );
}
