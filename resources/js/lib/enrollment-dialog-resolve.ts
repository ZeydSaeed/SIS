import {
    ADMISSION_BRANCH_OPTIONS,
    departmentsForBranch,
} from '@/components/admission/admission-branch-catalog';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import {
    SIS_CLASS_OPTIONS,
    SIS_SECTION_OPTIONS,
    resolveSisClassId,
    resolveSisSectionId,
    sisClassLabel,
    sisClassSelectOptions,
    sisSectionSelectOptions,
} from '@/lib/sis-class-section-options';

export type EnrollmentDialogDraft = {
    academic_year_id: string;
    branch_name: string;
    department_name: string;
    class_key: string;
    section_code: string;
    effective_from: string;
};

export type EnrollmentDialogFieldKey =
    | 'academic_year_id'
    | 'class_key'
    | 'section_code'
    | 'effective_from'
    | 'branch_name'
    | 'department_name';

export type EnrollmentDialogFieldErrors = Partial<Record<EnrollmentDialogFieldKey, string>>;

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
    fieldErrors?: EnrollmentDialogFieldErrors;
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

/** Shared section pick-list (enrollment + SIS section codes). */
export function admissionSectionSelectOptions(): Array<{ value: string; label: string }> {
    return sisSectionSelectOptions();
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

export function resolveDepartmentIdByName(
    departmentName: string,
    branchId: number | null,
    departments: EnrollmentFormFilterOptions['departments'],
): number | null {
    const name = departmentName.trim();
    if (name === '') {
        return null;
    }

    const candidates = departments.filter((department) => {
        if (branchId === null) {
            return true;
        }

        return department.branch_id === null || department.branch_id === branchId;
    });

    const exact = candidates.find((department) => department.name.trim() === name);
    if (exact !== undefined) {
        return exact.id;
    }

    const loose = candidates.find((department) => labelsLooselyMatch(department.name, name));

    return loose?.id ?? null;
}

export type EnrollmentDialogValidationInput = {
    students: Array<{ id: number; full_name: string; is_enrolled?: boolean }>;
    draft: EnrollmentDialogDraft;
    filterOptions: EnrollmentFormFilterOptions;
    messages: {
        emptyStudents: string;
        emptyYear: string;
        emptyBranch: string;
        emptyDepartment: string;
        emptyClass: string;
        emptySection: string;
        emptyEffectiveFrom: string;
        emptyFields: string;
        alreadyRegistered: string;
        alreadyRegisteredNamed: string;
        classNotFound: string;
        sectionNotFound: string;
        branchRequiredWithDept: string;
        branchMissingInYear: string;
        departmentMissingInBranch: string;
        noClassesInYear: string;
        noSectionsForClass: string;
        guideFillRequired: string;
        classLabel: string;
        sectionLabel: string;
    };
};

export type EnrollmentDialogResolvedPayload = {
    academicYearId: number;
    classId: number;
    sectionId: number;
    branchId: number | null;
    departmentId: number | null;
    effectiveFrom: string;
    eligibleStudents: Array<{ id: number; full_name: string }>;
};

function collectEmptyFieldErrors(
    students: EnrollmentDialogValidationInput['students'],
    draft: EnrollmentDialogDraft,
    messages: EnrollmentDialogValidationInput['messages'],
): { fieldErrors: EnrollmentDialogFieldErrors; details: string[] } {
    const fieldErrors: EnrollmentDialogFieldErrors = {};
    const details: string[] = [];

    if (students.length === 0) {
        details.push(messages.emptyStudents);
    }
    if (draft.academic_year_id.trim() === '') {
        fieldErrors.academic_year_id = messages.emptyYear;
        details.push(messages.emptyYear);
    }
    if (draft.branch_name.trim() === '') {
        fieldErrors.branch_name = messages.emptyBranch;
        details.push(messages.emptyBranch);
    }
    if (draft.department_name.trim() === '') {
        fieldErrors.department_name = messages.emptyDepartment;
        details.push(messages.emptyDepartment);
    }
    if (draft.class_key.trim() === '') {
        fieldErrors.class_key = messages.emptyClass;
        details.push(messages.emptyClass);
    }
    if (draft.section_code.trim() === '') {
        fieldErrors.section_code = messages.emptySection;
        details.push(messages.emptySection);
    }
    if (draft.effective_from.trim() === '') {
        fieldErrors.effective_from = messages.emptyEffectiveFrom;
        details.push(messages.emptyEffectiveFrom);
    }

    return { fieldErrors, details };
}

/**
 * Validate enrollment dialog draft before POST.
 * Returns the first blocking issue, or a resolved payload.
 */
export function validateEnrollmentDialog(
    input: EnrollmentDialogValidationInput,
): { ok: true; payload: EnrollmentDialogResolvedPayload } | { ok: false; issue: EnrollmentDialogIssue } {
    const { students, draft, filterOptions, messages } = input;
    const { fieldErrors, details: emptyDetails } = collectEmptyFieldErrors(
        students,
        draft,
        messages,
    );

    if (emptyDetails.length > 0) {
        return {
            ok: false,
            issue: {
                tone: 'warning',
                titleKey: 'enrollWarningTitle',
                descriptionKey: messages.emptyFields,
                details: emptyDetails,
                fieldErrors,
            },
        };
    }

    if (filterOptions.classes.length === 0) {
        return {
            ok: false,
            issue: {
                tone: 'error',
                titleKey: 'enrollDialogTitle',
                descriptionKey: messages.noClassesInYear,
                fieldErrors: { class_key: messages.noClassesInYear },
            },
        };
    }

    const alreadyRegistered = students.filter((student) => student.is_enrolled === true);
    if (alreadyRegistered.length > 0) {
        return {
            ok: false,
            issue: {
                tone: 'error',
                titleKey: 'enrollAlreadyRegisteredTitle',
                descriptionKey: messages.alreadyRegistered,
                details: alreadyRegistered.map((student) =>
                    messages.alreadyRegisteredNamed.replace('{name}', student.full_name),
                ),
            },
        };
    }

    const classId = resolveSisClassId(draft.class_key, filterOptions.classes);
    if (classId === null) {
        return {
            ok: false,
            issue: {
                tone: 'error',
                titleKey: 'enrollDialogTitle',
                descriptionKey: messages.classNotFound,
                details: [
                    `${messages.classLabel}: ${sisClassLabel(draft.class_key) || draft.class_key}`,
                ],
                fieldErrors: { class_key: messages.classNotFound },
            },
        };
    }

    const sectionId = resolveSisSectionId(
        draft.section_code,
        classId,
        filterOptions.sections,
    );
    if (sectionId === null) {
        const hasAnyForClass = filterOptions.sections.some(
            (section) => section.class_id === classId,
        );

        return {
            ok: false,
            issue: {
                tone: 'error',
                titleKey: 'enrollDialogTitle',
                descriptionKey: hasAnyForClass
                    ? messages.sectionNotFound
                    : messages.noSectionsForClass,
                details: [`${messages.sectionLabel}: ${draft.section_code}`],
                fieldErrors: {
                    section_code: hasAnyForClass
                        ? messages.sectionNotFound
                        : messages.noSectionsForClass,
                },
            },
        };
    }

    if (draft.department_name.trim() !== '' && draft.branch_name.trim() === '') {
        return {
            ok: false,
            issue: {
                tone: 'warning',
                titleKey: 'enrollWarningTitle',
                descriptionKey: messages.branchRequiredWithDept,
                fieldErrors: {
                    branch_name: messages.branchRequiredWithDept,
                },
            },
        };
    }

    let branchId: number | null = null;
    if (draft.branch_name.trim() !== '') {
        branchId = resolveBranchIdByName(draft.branch_name, filterOptions.branches);
        if (branchId === null) {
            return {
                ok: false,
                issue: {
                    tone: 'error',
                    titleKey: 'enrollDialogTitle',
                    descriptionKey: messages.branchMissingInYear,
                    fieldErrors: { branch_name: messages.branchMissingInYear },
                },
            };
        }
    }

    let departmentId: number | null = null;
    if (draft.department_name.trim() !== '') {
        departmentId = resolveDepartmentIdByName(
            draft.department_name,
            branchId,
            filterOptions.departments,
        );
        if (departmentId === null) {
            return {
                ok: false,
                issue: {
                    tone: 'error',
                    titleKey: 'enrollDialogTitle',
                    descriptionKey: messages.departmentMissingInBranch,
                    fieldErrors: { department_name: messages.departmentMissingInBranch },
                },
            };
        }
    }

    return {
        ok: true,
        payload: {
            academicYearId: Number(draft.academic_year_id),
            classId,
            sectionId,
            branchId,
            departmentId,
            effectiveFrom: draft.effective_from.trim(),
            eligibleStudents: students
                .filter((student) => student.is_enrolled !== true)
                .map((student) => ({ id: student.id, full_name: student.full_name })),
        },
    };
}

export function enrollmentGuideIssue(message: string): EnrollmentDialogIssue {
    return {
        tone: 'info',
        titleKey: 'enrollGuideTitle',
        descriptionKey: message,
    };
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

export function sectionCodeFromSectionName(name: string | null | undefined): string {
    const value = (name ?? '').trim();
    if (value === '') {
        return '';
    }

    const upper = value.toUpperCase();
    for (const item of SIS_SECTION_OPTIONS) {
        if (item.value === upper || item.label === value) {
            return item.value;
        }
    }

    if (value === 'أ' || value === 'ا' || value.includes('شعبة أ') || value.includes('شعبة ا')) {
        return 'A';
    }
    if (value === 'ب' || value.includes('شعبة ب')) {
        return 'B';
    }
    if (value === 'ج' || value.includes('شعبة ج')) {
        return 'C';
    }

    return '';
}

export function branchNameFromStudentPlacement(
    student: {
        branch_id?: number | null;
        branch_name?: string | null;
        department_name?: string | null;
    },
    branches: EnrollmentFormFilterOptions['branches'],
): string {
    const fromName = (student.branch_name ?? '').trim();
    if (fromName !== '') {
        const catalog = ADMISSION_BRANCH_OPTIONS.find((name) => labelsLooselyMatch(name, fromName));
        if (catalog !== undefined) {
            return catalog;
        }

        const org = branches.find((branch) => labelsLooselyMatch(branch.name, fromName));
        if (org !== undefined) {
            const matched = ADMISSION_BRANCH_OPTIONS.find((name) =>
                labelsLooselyMatch(name, org.name),
            );

            return matched ?? org.name;
        }

        return fromName;
    }

    if (student.branch_id != null && student.branch_id > 0) {
        const org = branches.find((branch) => branch.id === student.branch_id);
        if (org !== undefined) {
            const matched = ADMISSION_BRANCH_OPTIONS.find((name) =>
                labelsLooselyMatch(name, org.name),
            );

            return matched ?? org.name;
        }
    }

    return '';
}

export function departmentNameFromStudentPlacement(
    departmentName: string | null | undefined,
    branchName: string,
): string {
    const value = (departmentName ?? '').trim();
    if (value === '' || branchName.trim() === '') {
        return '';
    }

    const options = departmentsForBranch(branchName);
    const match = options.find((name) => labelsLooselyMatch(name, value));

    return match ?? value;
}

/** Prefill enrollment draft fields from the first selected student's saved placement. */
export function draftPlacementFromStudents(
    students: Array<{
        branch_id?: number | null;
        branch_name?: string | null;
        department_name?: string | null;
        admitted_class_name?: string | null;
        section_name?: string | null;
    }>,
    branches: EnrollmentFormFilterOptions['branches'],
): Pick<EnrollmentDialogDraft, 'branch_name' | 'department_name' | 'class_key' | 'section_code'> {
    const student = students[0];
    if (student === undefined) {
        return {
            branch_name: '',
            department_name: '',
            class_key: '',
            section_code: '',
        };
    }

    const branchName = branchNameFromStudentPlacement(student, branches);

    return {
        branch_name: branchName,
        department_name: departmentNameFromStudentPlacement(student.department_name, branchName),
        class_key: classKeyFromAdmittedClassName(student.admitted_class_name),
        section_code: sectionCodeFromSectionName(student.section_name),
    };
}
