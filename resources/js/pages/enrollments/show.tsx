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
    specialization_id?: number | null;
    student_code?: string | null;
    student_full_name?: string | null;
    student_first_name?: string | null;
    student_father_name?: string | null;
    student_grandfather_name?: string | null;
    student_great_grandfather_name?: string | null;
    student_last_name?: string | null;
    student_gender?: number | null;
    student_birth_date?: string | null;
    school_name?: string | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
    class_code?: string | null;
    class_name?: string | null;
    section_code?: string | null;
    section_name?: string | null;
    specialization_code?: string | null;
    specialization_name?: string | null;
    grade_level_code?: string | null;
    grade_level_name?: string | null;
    department_name?: string | null;
    stage_name?: string | null;
};

type PageProps = {
    enrollment: Enrollment;
};

function textOrDash(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function studentName(enrollment: Enrollment): string {
    const parts = [
        enrollment.student_first_name,
        enrollment.student_father_name,
        enrollment.student_grandfather_name,
        enrollment.student_great_grandfather_name,
        enrollment.student_last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '');

    if (parts.length > 0) {
        return parts.join(' ');
    }

    return enrollment.student_full_name?.trim() || String(enrollment.student_id);
}

function genderLabel(gender: number | null | undefined, i18n: ReturnType<typeof t>): string {
    if (gender === 1) {
        return i18n.students.male;
    }

    if (gender === 2) {
        return i18n.students.female;
    }

    return '—';
}

function statusLabel(status: number, i18n: ReturnType<typeof t>): string {
    if (status === 1) {
        return i18n.status.active;
    }

    if (status === 2) {
        return i18n.status.cancelled;
    }

    if (status === 3) {
        return i18n.status.transferred;
    }

    return String(status);
}

export default function EnrollmentShow({ enrollment }: PageProps) {
    const i18n = t();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: i18n.enrollments.title, href: '/enrollments' },
        { title: enrollment.enrollment_number, href: `/enrollments/${enrollment.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={enrollment.enrollment_number} />
            <div className="sis-ops-hub sis-admission-page sis-students-page flex flex-col gap-4 p-4" dir="rtl" lang="ar">
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
                <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.enrollmentNumber}</dt>
                        <dd>
                            <span dir="ltr">{enrollment.enrollment_number}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.student}</dt>
                        <dd>{studentName(enrollment)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.studentCode}</dt>
                        <dd>
                            <span dir="ltr">{textOrDash(enrollment.student_code)}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.gender}</dt>
                        <dd>{genderLabel(enrollment.student_gender, i18n)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.students.birthDate}</dt>
                        <dd>
                            <span dir="ltr">{textOrDash(enrollment.student_birth_date)}</span>
                        </dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.academicYear}</dt>
                        <dd>{textOrDash(enrollment.academic_year_name ?? enrollment.academic_year_code)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.className}</dt>
                        <dd>{textOrDash(enrollment.class_name ?? enrollment.class_code)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.sectionName}</dt>
                        <dd>{textOrDash(enrollment.section_name ?? enrollment.section_code)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.gradeLevel}</dt>
                        <dd>{textOrDash(enrollment.grade_level_name ?? enrollment.grade_level_code)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.specialization}</dt>
                        <dd>{textOrDash(enrollment.specialization_name)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.departmentName}</dt>
                        <dd>{textOrDash(enrollment.department_name)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.stageName}</dt>
                        <dd>{textOrDash(enrollment.stage_name)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.enrollments.schoolName}</dt>
                        <dd>{textOrDash(enrollment.school_name)}</dd>
                    </div>
                    <div>
                        <dt className="opacity-70">{i18n.common.status}</dt>
                        <dd>{statusLabel(enrollment.status, i18n)}</dd>
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
                </dl>
            </div>
        </AppLayout>
    );
}
