import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ErrorState } from '@/components/sis/error-state';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import { t } from '@/i18n';

export type StudentDetail = {
    id: number;
    student_code: string;
    full_name: string;
    first_name: string;
    middle_name?: string | null;
    last_name: string;
    gender: number;
    birth_date: string;
    birth_place?: string | null;
    nationality?: string | null;
    national_id?: string | null;
    status: number;
    created_at: string;
    updated_at: string;
};

export type StudentAuthorization = {
    canView: boolean;
    canViewPii: boolean;
    canUpdate: boolean;
};

type StudentDetailsSurfaceProps = {
    student?: StudentDetail | null;
    authorization?: StudentAuthorization | null;
    error?: 'not_found' | 'forbidden' | null;
    showProfileLink?: boolean;
};

function DetailField({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="grid gap-1">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide">{label}</dt>
            <dd className="text-sm">{value ?? '—'}</dd>
        </div>
    );
}

export function StudentDetailsSurface({
    student,
    authorization,
    error = null,
    showProfileLink = true,
}: StudentDetailsSurfaceProps) {
    const i18n = t();

    if (error === 'forbidden') {
        return (
            <ErrorState
                title={i18n.students.accessDenied}
                description={i18n.students.accessDeniedDesc}
            />
        );
    }

    if (error === 'not_found' || !student) {
        return (
            <ErrorState
                title={i18n.students.notFound}
                description={i18n.students.notFoundDesc}
            />
        );
    }

    const genderLabel =
        student.gender === 1
            ? i18n.students.male
            : student.gender === 2
              ? i18n.students.female
              : String(student.gender);

    return (
        <div className="flex flex-col gap-6" dir="rtl" lang="ar">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold">{student.full_name}</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {i18n.students.code}:{' '}
                        <span dir="ltr">{student.student_code}</span>
                    </p>
                </div>
                <StudentStatusBadge status={student.status} />
            </div>

            <dl className="grid gap-4 sm:grid-cols-2">
                <DetailField label={i18n.students.birthDate} value={student.birth_date} />
                <DetailField label={i18n.students.gender} value={genderLabel} />
                <DetailField label={i18n.students.nationality} value={student.nationality} />
                <DetailField label={i18n.students.birthPlace} value={student.birth_place} />
                {authorization?.canViewPii ? (
                    <DetailField label={i18n.students.nationalId} value={student.national_id} />
                ) : null}
                <DetailField label={i18n.students.updated} value={student.updated_at} />
            </dl>

            {showProfileLink ? (
                <Button asChild variant="outline" className="w-fit">
                    <Link href={`/students/${student.id}`}>{i18n.students.openProfile}</Link>
                </Button>
            ) : null}
        </div>
    );
}
