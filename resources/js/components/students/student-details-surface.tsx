import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ErrorState } from '@/components/sis/error-state';
import { formatAcademicYearOptionLabel } from '@/components/sis/ops-year-filter';
import { StudentStatusBadge } from '@/components/students/student-status-badge';
import { t } from '@/i18n';

export type StudentDetail = {
    id: number;
    student_code: string;
    full_name: string;
    first_name: string;
    middle_name?: string | null;
    father_name?: string | null;
    grandfather_name?: string | null;
    great_grandfather_name?: string | null;
    last_name: string;
    mother_name?: string | null;
    maternal_father_name?: string | null;
    maternal_grandfather_name?: string | null;
    guardian_triple_name?: string | null;
    gender: number;
    birth_date: string;
    mawalid_date?: string | null;
    birth_place?: string | null;
    nationality?: string | null;
    governorate?: string | null;
    neighborhood?: string | null;
    locality?: string | null;
    house_number?: string | null;
    registration_place?: string | null;
    religion?: number;
    national_id?: string | null;
    previous_school_name?: string | null;
    transfer_document_number?: number | null;
    transfer_document_date?: string | null;
    school_start_date?: string | null;
    admitted_class_name?: string | null;
    notes?: string | null;
    mobile?: string | null;
    guardian_mobile?: string | null;
    email?: string | null;
    school_name?: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
    department_name?: string | null;
    specialization_name?: string | null;
    stage_name?: string | null;
    section_name?: string | null;
    academic_year_id?: number | null;
    academic_year_name?: string | null;
    academic_year_code?: string | null;
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

function formatCivilDate(value: string | null | undefined): string | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    return `${match[2]}/${match[3]}/${match[1]}`;
}

function DetailField({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div className="grid gap-1">
            <dt className="text-muted-foreground text-xs font-medium tracking-wide">{label}</dt>
            <dd className="text-sm whitespace-pre-wrap">{value ?? '—'}</dd>
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

    const religion = student.religion ?? 1;
    const religionLabel =
        religion === 1
            ? i18n.students.religionMuslim
            : religion === 2
              ? i18n.students.religionChristian
              : religion === 3
                ? i18n.students.religionOther
                : String(religion);

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
                <DetailField label={i18n.students.firstName} value={student.first_name} />
                <DetailField label={i18n.students.fatherName} value={student.father_name} />
                <DetailField label={i18n.students.grandfatherName} value={student.grandfather_name} />
                <DetailField
                    label={i18n.students.greatGrandfatherName}
                    value={student.great_grandfather_name}
                />
                <DetailField label={i18n.students.familyName} value={student.last_name} />
                <DetailField label={i18n.students.motherName} value={student.mother_name} />
                <DetailField
                    label={i18n.students.maternalFatherName}
                    value={student.maternal_father_name}
                />
                <DetailField
                    label={i18n.students.maternalGrandfatherName}
                    value={student.maternal_grandfather_name}
                />
                <DetailField
                    label={i18n.students.guardianTripleName}
                    value={student.guardian_triple_name}
                />
                <DetailField label={i18n.students.governorate} value={student.governorate} />
                <DetailField label={i18n.students.neighborhood} value={student.neighborhood} />
                <DetailField label={i18n.students.locality} value={student.locality} />
                <DetailField label={i18n.students.houseNumber} value={student.house_number} />
                <DetailField
                    label={i18n.students.birthDate}
                    value={formatCivilDate(student.birth_date)}
                />
                <DetailField
                    label={i18n.students.registrationPlace}
                    value={student.registration_place}
                />
                <DetailField label={i18n.students.gender} value={genderLabel} />
                <DetailField label={i18n.students.nationality} value={student.nationality} />
                <DetailField label={i18n.students.religion} value={religionLabel} />
                <DetailField
                    label={i18n.students.mawalidDate}
                    value={formatCivilDate(student.mawalid_date)}
                />
                {authorization?.canViewPii ? (
                    <DetailField label={i18n.students.nationalId} value={student.national_id} />
                ) : null}
                <DetailField
                    label={i18n.students.previousSchoolName}
                    value={student.previous_school_name}
                />
                <DetailField
                    label={i18n.students.transferDocumentNumber}
                    value={
                        student.transfer_document_number != null
                            ? String(student.transfer_document_number)
                            : null
                    }
                />
                <DetailField
                    label={i18n.students.transferDocumentDate}
                    value={student.transfer_document_date}
                />
                <DetailField
                    label={i18n.students.schoolStartDate}
                    value={student.school_start_date}
                />
                <DetailField
                    label={i18n.students.admittedClassName}
                    value={student.admitted_class_name}
                />
                {authorization?.canViewPii ? (
                    <>
                        <DetailField label={i18n.students.mobile} value={student.mobile} />
                        <DetailField
                            label={i18n.students.guardianMobile}
                            value={student.guardian_mobile}
                        />
                        <DetailField label={i18n.students.email} value={student.email} />
                    </>
                ) : null}
                <DetailField label={i18n.students.schoolName} value={student.school_name} />
                <DetailField label={i18n.students.branchName} value={student.branch_name} />
                <DetailField
                    label={i18n.students.academicYear}
                    value={
                        formatAcademicYearOptionLabel(
                            student.academic_year_name ?? '',
                            student.academic_year_code ?? '',
                        ) || null
                    }
                />
                <DetailField
                    label={i18n.students.departmentName}
                    value={student.department_name}
                />
                <DetailField
                    label={i18n.students.specialization}
                    value={student.specialization_name}
                />
                <DetailField label={i18n.students.stageName} value={student.stage_name} />
                <DetailField label={i18n.students.sectionName} value={student.section_name} />
                <DetailField label={i18n.students.updated} value={student.updated_at} />
                <div className="sm:col-span-2">
                    <DetailField label={i18n.students.notes} value={student.notes} />
                </div>
            </dl>

            {showProfileLink ? (
                <Button asChild variant="outline" className="w-fit">
                    <Link href={`/students/${student.id}`}>{i18n.students.openProfile}</Link>
                </Button>
            ) : null}
        </div>
    );
}
