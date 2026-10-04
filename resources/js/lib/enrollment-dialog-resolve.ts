import {
    ADMISSION_BRANCH_OPTIONS,
    departmentsForBranch,
} from '@/components/admission/admission-branch-catalog';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import { SIS_CLASS_OPTIONS, sisClassSelectOptions } from '@/lib/sis-class-section-options';

export type EnrollmentDialogIssueTone = 'error' | 'warning' | 'info';

export type EnrollmentDialogIssue = {
    tone: EnrollmentDialogIssueTone;
    titleKey:
        | 'enrollDialogTitle'
        | 'enrollAlreadyRegisteredTitle'
        | 'enrollGuideTitle'
        | 'enrollWarningTitle';
    descriptionKey: string;
    details?: string[];
    /** Field name → message for in-form highlighting. */
    fieldErrors?: Partial<Record<string, string>>;
};

/** Normalize Arabic labels for tolerant branch/department matching. */
export function normalizeArabicLabel(value: string): string {
    return value
        .trim()
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .replace(/ئ/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/[\u064B-\u065F\u0670]/g, '')
        .replace(/^(ال)+/u, '')
        .replace(/\s+/g, ' ')
        .toLowerCase();
}

export function labelsLooselyMatch(left: string, right: string): boolean {
    const a = normalizeArabicLabel(left);
    const b = normalizeArabicLabel(right);
    if (a === '' || b === '') {
        return false;
    }

    return a === b || a.includes(b) || b.includes(a);
}

/** Same pick-list source as admission new-request form. */
export function admissionBranchSelectOptions(): Array<{ value: string; label: string }> {
    return ADMISSION_BRANCH_OPTIONS.map((name) => ({ value: name, label: name }));
}

/** Same department pick-list source as admission new-request form. */
export function admissionDepartmentSelectOptions(
    branchName: string,
): Array<{ value: string; label: string }> {
    return departmentsForBranch(branchName).map((name) => ({ value: name, label: name }));
}

/** Same class pick-list source as admission new-request form. */
export function admissionClassSelectOptions(): Array<{ value: string; label: string }> {
    return sisClassSelectOptions();
}

export function resolveBranchIdByName(
    branchName: string,
    branches: EnrollmentFormFilterOptions['branches'],
): number | null {
    const name = branchName.trim();
    if (name === '') {
        return null;
    }

    const exact = branches.find((branch) => branch.name.trim() === name);
    if (exact !== undefined) {
        return exact.id;
    }

    const loose = branches.find((branch) => labelsLooselyMatch(branch.name, name));

    return loose?.id ?? null;
}

export function classKeyFromAdmittedClassName(name: string | null | undefined): string {
    const value = (name ?? '').trim();
    if (value === '') {
        return '';
    }

    const byValue = SIS_CLASS_OPTIONS.find((item) => item.value === value);
    if (byValue !== undefined) {
        return byValue.value;
    }

    const byLabel = SIS_CLASS_OPTIONS.find((item) => labelsLooselyMatch(item.label, value));

    return byLabel?.value ?? '';
}
