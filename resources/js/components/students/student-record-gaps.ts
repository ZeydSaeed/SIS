import { t } from '@/i18n';

export type StudentProfileGapSource = Record<string, unknown>;

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

/**
 * Empty student-form field labels.
 * - Skips keys removed by PII sanitization (absent ≠ empty).
 * - Transfer fields required only when any transfer value is present.
 * - Email is optional.
 */
export function studentRecordCompletenessGaps(source: StudentProfileGapSource): string[] {
    const s = t().students;
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

    requireOwned('first_name', s.firstName);
    requireOwned('father_name', s.fatherName);
    requireOwned('grandfather_name', s.grandfatherName);
    requireOwned('great_grandfather_name', s.greatGrandfatherName);
    requireOwned('last_name', s.familyName);
    requireOwned('mother_name', s.motherName);
    requireOwned('maternal_father_name', s.maternalFatherName);
    requireOwned('maternal_grandfather_name', s.maternalGrandfatherName);
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

    requireOwned('governorate', s.governorate);
    requireOwned('neighborhood', s.neighborhood);
    requireOwned('locality', s.locality);
    requireOwned('house_number', s.houseNumber);

    requireOwned('school_name', s.schoolName);
    requireOwned('academic_year_id', s.academicYear);
    requireOwned('school_start_date', s.schoolStartDate);

    const previousSchool = hasOwn(source, 'previous_school_name')
        ? source.previous_school_name
        : null;
    const transferNumber = hasOwn(source, 'transfer_document_number')
        ? source.transfer_document_number
        : null;
    const transferDate = hasOwn(source, 'transfer_document_date')
        ? source.transfer_document_date
        : null;
    const hasAnyTransfer =
        !isBlank(previousSchool) || !isBlank(transferNumber) || !isBlank(transferDate);
    if (hasAnyTransfer) {
        require(previousSchool, s.previousSchoolName);
        require(transferNumber, s.transferDocumentNumber);
        require(transferDate, s.transferDocumentDate);
    }

    requireOwned('guardian_triple_name', s.guardianTripleName);
    requireOwned('mobile', s.mobile);
    requireOwned('guardian_mobile', s.guardianMobile);
    // email optional — not required for مستوفي

    return gaps;
}

export function isStudentRecordComplete(source: StudentProfileGapSource): boolean {
    return studentRecordCompletenessGaps(source).length === 0;
}
