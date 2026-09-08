import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ErrorState } from '@/components/sis/error-state';
import { StudentStatusBadge } from '@/components/students/student-status-badge';

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
            <dt className="text-muted-foreground text-xs font-medium uppercase tracking-wide">{label}</dt>
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
    if (error === 'forbidden') {
        return <ErrorState title="Access denied" description="You are not allowed to view this student." />;
    }

    if (error === 'not_found' || !student) {
        return <ErrorState title="Student not found" description="The requested student record does not exist." />;
    }

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-xl font-semibold">{student.full_name}</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Code: <span dir="ltr">{student.student_code}</span>
                    </p>
                </div>
                <StudentStatusBadge status={student.status} />
            </div>

            <dl className="grid gap-4 sm:grid-cols-2">
                <DetailField label="Birth date" value={student.birth_date} />
                <DetailField label="Gender" value={student.gender === 1 ? 'Male' : student.gender === 2 ? 'Female' : String(student.gender)} />
                <DetailField label="Nationality" value={student.nationality} />
                <DetailField label="Birth place" value={student.birth_place} />
                {authorization?.canViewPii ? (
                    <DetailField label="National ID" value={student.national_id} />
                ) : null}
                <DetailField label="Updated" value={student.updated_at} />
            </dl>

            {authorization?.canUpdate ? (
                <p className="text-muted-foreground text-xs">
                    Edit actions are deferred to a later phase — authorization is server-derived only.
                </p>
            ) : null}

            {showProfileLink ? (
                <Button asChild variant="outline" size="sm" className="self-start">
                    <Link href={`/students/${student.id}`}>Open full profile</Link>
                </Button>
            ) : null}
        </div>
    );
}
