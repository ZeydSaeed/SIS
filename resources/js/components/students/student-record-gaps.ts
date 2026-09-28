import { t } from '@/i18n';

export type StudentProfileGapSource = Record<string, unknown>;

/** Same slots as student-record-form document section. */
export const STUDENT_REQUIRED_DOCUMENT_TYPES = [
    { type: 20, labelKey: 'docPersonalPhoto' as const },
    { type: 11, labelKey: 'docStudentIdFront' as const },
    { type: 12, labelKey: 'docStudentIdBack' as const },
    { type: 13, labelKey: 'docFatherIdFront' as const },
    { type: 14, labelKey: 'docFatherIdBack' as const },
    { type: 15, labelKey: 'docMotherIdFront' as const },
    { type: 16, labelKey: 'docMotherIdBack' as const },
    { type: 17, labelKey: 'docResidenceFront' as const },
    { type: 18, labelKey: 'docResidenceBack' as const },
    { type: 19, labelKey: 'docGraduationCertificate' as const },
] as const;

function isBlank(value: unknown): boolean {
    if (value === null || value === undefined) {
        return true;
    }

    if (typeof value === 'number') {
        return !Number.isFinite(value);
    }

    return String(value).trim() === '';
}

function hasOwn(source: StudentProfileGapSource, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(source, key);
}

function uploadedDocumentTypes(source: StudentProfileGapSource): Set<number> {
    const raw = source.documents;
    if (!Array.isArray(raw)) {
        return new Set();
    }

    const types = new Set<number>();
    for (const item of raw) {
        if (item === null || typeof item !== 'object') {
            continue;
        }

        const type = Number((item as { document_type?: unknown }).document_type);
        if (Number.isFinite(type) && type > 0) {
            types.add(type);
        }
    }

    return types;
}

/**
 * Empty student-form field labels (including المستمسكات).
 * Excludes: البريد الإلكتروني، الملاحظات.
 * - Skips keys removed by PII sanitization (absent ≠ empty).
 * - Grades only count when the key is present and blank (hidden when never filled).
 * - Documents count when `documents` key is present (list / detail payloads).
 */
export function studentRecordCompletenessGaps(source: StudentProfileGapSource): string[] {
    const s = t().students;
    const a = t().admission;
    const gaps: string[] = [];
    const require = (value: unknown, label: string): void => {
        if (isBlank(value)) {
            gaps.push(label);
        }
    };
    /** Require only when the payload includes the key (not stripped / not loaded). */
    const requireOwned = (key: string, label: string): void => {
        if (!hasOwn(source, key)) {
            return;
        }

        require(source[key], label);
    };

    const v = (key: string): unknown => (hasOwn(source, key) ? source[key] : null);
    const gender = Number(v('gender') ?? 0);
    const religion = Number(v('religion') ?? 0);
    const administrativeUnit = Number(v('administrative_unit') ?? 0);
    const previousStudyTrack = Number(v('previous_study_track') ?? 0);

    // —— Registration / school ——
    requireOwned('academic_year_id', s.academicYear);
    requireOwned('school_name', s.schoolName);
    if (hasOwn(source, 'branch_id') || hasOwn(source, 'branch_name')) {
        const branchFilled =
            !isBlank(v('branch_id')) || !isBlank(v('branch_name'));
        if (!branchFilled) {
            gaps.push(s.branchName);
        }
    }
    requireOwned('department_name', s.departmentName);
    requireOwned('admitted_class_name', a.gradeLevel);
    requireOwned('school_start_date', s.schoolStartDate);

    // —— Personal ——
    requireOwned('first_name', s.firstName);
    requireOwned('father_name', s.fatherName);
    requireOwned('grandfather_name', s.grandfatherName);
    requireOwned('great_grandfather_name', s.greatGrandfatherName);
    requireOwned('last_name', s.familyName);
    requireOwned('birth_date', s.birthDate);
    requireOwned('birth_place', s.birthPlace);
    if (hasOwn(source, 'gender') && gender !== 1 && gender !== 2) {
        gaps.push(s.gender);
    }
    requireOwned('nationality', s.nationality);
    if (hasOwn(source, 'religion') && religion !== 1 && religion !== 2 && religion !== 3) {
        gaps.push(s.religion);
    }
    requireOwned('national_id', s.nationalId);
    requireOwned('mawalid_date', s.mawalidDate);
    requireOwned('registration_place', s.registrationPlace);

    // —— Parents ——
    requireOwned('mother_name', s.motherName);
    requireOwned('maternal_father_name', s.maternalFatherName);
    requireOwned('maternal_grandfather_name', s.maternalGrandfatherName);
    requireOwned('guardian_triple_name', s.guardianTripleName);
    requireOwned('father_occupation', s.fatherOccupation);
    requireOwned('mother_occupation', s.motherOccupation);

    // —— Residence / contact (email excluded) ——
    requireOwned('governorate', s.governorate);
    requireOwned('neighborhood', s.neighborhood);
    if (hasOwn(source, 'administrative_unit') && ![1, 2, 3].includes(administrativeUnit)) {
        gaps.push(s.administrativeUnit);
    }
    requireOwned('locality', s.locality);
    requireOwned('house_number', s.houseNumber);
    requireOwned('mobile', s.mobile);
    requireOwned('guardian_mobile', s.guardianMobile);
    // email optional — excluded from متابعة الملف count
    // notes optional — excluded from متابعة الملف count

    // —— Prior study (always on the form) ——
    requireOwned('previous_school_name', s.previousSchoolName);
    requireOwned('graduation_year', s.graduationYear);
    requireOwned('previous_gpa', s.previousGpa);
    if (hasOwn(source, 'previous_study_track') && ![1, 2, 3, 4, 5].includes(previousStudyTrack)) {
        gaps.push(s.previousStudyTrack);
    }
    requireOwned('transfer_document_number', s.transferDocumentNumber);
    requireOwned('transfer_document_date', s.transferDocumentDate);
    // mathematics_grade / physics_grade: shown only when filled — blanks do not count

    // —— Documents ——
    if (hasOwn(source, 'documents')) {
        const uploaded = uploadedDocumentTypes(source);
        for (const slot of STUDENT_REQUIRED_DOCUMENT_TYPES) {
            if (!uploaded.has(slot.type)) {
                gaps.push(a[slot.labelKey]);
            }
        }
    }

    return gaps;
}

export function isStudentRecordComplete(source: StudentProfileGapSource): boolean {
    return studentRecordCompletenessGaps(source).length === 0;
}
