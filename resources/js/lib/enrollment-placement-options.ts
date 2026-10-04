import { ADMISSION_BRANCH_OPTIONS, departmentsForBranch } from '@/components/admission/admission-branch-catalog';
import type { EnrollmentFormFilterOptions } from '@/components/enrollments/enrollment-record-form';
import type { EnrollmentDialogIssue } from '@/lib/enrollment-dialog-resolve';
import {
    SIS_CLASS_OPTIONS,
    SIS_SECTION_OPTIONS,
    resolveSisClassId,
    resolveSisSectionId,
} from '@/lib/sis-class-section-options';

/**
 * Enrollment placement pick-lists built from the school's real records (ids from
 * filterOptions) — no name matching on submit. Labels keep the established UI text
 * (catalog class / section labels) when a record maps to the catalog.
 */
export type PlacementDraft = {
    academic_year_id: string;
    branch_id: string;
    department_id: string;
    class_id: string;
    section_id: string;
    effective_from: string;
};

export type PlacementFieldKey = keyof PlacementDraft;

export type PlacementOption = { value: string; label: string };

type Candidate = {
    id: number;
    full_name: string;
    is_enrolled?: boolean;
    branch_id?: number | null;
    department_id?: number | null;
    grade_level_id?: number | null;
    department_name?: string | null;
    admitted_class_name?: string | null;
    section_name?: string | null;
};

function catalogRank(order: readonly string[], name: string): number {
    const index = order.indexOf(name.trim());

    return index === -1 ? order.length : index;
}

/** Distinct by visible name (legacy duplicate records collapse to the first id). */
function distinctByLabel(options: PlacementOption[]): PlacementOption[] {
    const seen = new Set<string>();

    return options.filter((option) => {
        if (seen.has(option.label)) {
            return false;
        }
        seen.add(option.label);

        return true;
    });
}

export function placementBranchOptions(filterOptions: EnrollmentFormFilterOptions): PlacementOption[] {
    const order: readonly string[] = ADMISSION_BRANCH_OPTIONS;

    return distinctByLabel(
        [...filterOptions.branches]
            .sort((a, b) => catalogRank(order, a.name) - catalogRank(order, b.name) || a.id - b.id)
            .map((branch) => ({ value: String(branch.id), label: branch.name })),
    );
}

export function placementDepartmentOptions(
    filterOptions: EnrollmentFormFilterOptions,
    branchId: string,
): PlacementOption[] {
    const branch = filterOptions.branches.find((item) => String(item.id) === branchId);
    if (branch === undefined) {
        return [];
    }
    const order = departmentsForBranch(branch.name);

    return distinctByLabel(
        filterOptions.departments
            .filter((department) => department.branch_id === branch.id)
            .sort((a, b) => catalogRank(order, a.name) - catalogRank(order, b.name) || a.id - b.id)
            .map((department) => ({ value: String(department.id), label: department.name })),
    );
}

export function placementClassOptions(filterOptions: EnrollmentFormFilterOptions): PlacementOption[] {
    const labelById = new Map<number, string>();
    for (const option of SIS_CLASS_OPTIONS) {
        const id = resolveSisClassId(option.value, filterOptions.classes);
        if (id !== null && !labelById.has(id)) {
            labelById.set(id, option.label);
        }
    }

    const catalogLabels = SIS_CLASS_OPTIONS.map((option) => option.label as string);

    return distinctByLabel(
        filterOptions.classes
            .map((item) => ({ value: String(item.id), label: labelById.get(item.id) ?? item.name }))
            .sort((a, b) => catalogRank(catalogLabels, a.label) - catalogRank(catalogLabels, b.label)),
    );
}

export function placementSectionOptions(
    filterOptions: EnrollmentFormFilterOptions,
    classId: string,
): PlacementOption[] {
    const id = Number(classId);
    if (!Number.isInteger(id) || id < 1) {
        return [];
    }

    const labelById = new Map<number, string>();
    for (const option of SIS_SECTION_OPTIONS) {
        const sectionId = resolveSisSectionId(option.value, id, filterOptions.sections);
        if (sectionId !== null && !labelById.has(sectionId)) {
            labelById.set(sectionId, option.label);
        }
    }

    const catalogLabels = SIS_SECTION_OPTIONS.map((option) => option.label as string);

    return distinctByLabel(
        filterOptions.sections
            .filter((section) => section.class_id === id)
            .map((section) => ({ value: String(section.id), label: labelById.get(section.id) ?? section.name }))
            .sort((a, b) => catalogRank(catalogLabels, a.label) - catalogRank(catalogLabels, b.label)),
    );
}

function hasOption(options: PlacementOption[], value: string): boolean {
    return options.some((option) => option.value === value);
}

/** Prefill from the first selected student's structured placement (ids), names only as fallback. */
export function prefillPlacement(
    students: Candidate[],
    filterOptions: EnrollmentFormFilterOptions,
): Pick<PlacementDraft, 'branch_id' | 'department_id' | 'class_id' | 'section_id'> {
    const empty = { branch_id: '', department_id: '', class_id: '', section_id: '' };
    const student = students[0];
    if (student === undefined) {
        return empty;
    }

    const branches = placementBranchOptions(filterOptions);
    const branchId =
        student.branch_id != null && hasOption(branches, String(student.branch_id))
            ? String(student.branch_id)
            : '';

    const departments = placementDepartmentOptions(filterOptions, branchId);
    const byId =
        student.department_id != null && hasOption(departments, String(student.department_id))
            ? String(student.department_id)
            : '';
    const byName =
        departments.find((option) => option.label === (student.department_name ?? '').trim())?.value ?? '';

    const classes = placementClassOptions(filterOptions);
    const classByGrade =
        student.grade_level_id != null
            ? filterOptions.classes.find((item) => item.grade_level_id === student.grade_level_id)
            : undefined;
    const classId =
        classByGrade !== undefined && hasOption(classes, String(classByGrade.id))
            ? String(classByGrade.id)
            : (classes.find((option) => option.label === (student.admitted_class_name ?? '').trim())
                  ?.value ?? '');

    const sectionId =
        placementSectionOptions(filterOptions, classId).find(
            (option) => option.label === (student.section_name ?? '').trim(),
        )?.value ?? '';

    return { branch_id: branchId, department_id: byId || byName, class_id: classId, section_id: sectionId };
}

export type PlacementValidationMessages = {
    emptyStudents: string;
    emptyYear: string;
    emptyBranch: string;
    emptyDepartment: string;
    emptyClass: string;
    emptySection: string;
    emptyEffectiveFrom: string;
    alreadyRegistered: string;
    alreadyRegisteredNamed: string;
    classNotFound: string;
    sectionNotFound: string;
    branchMissingInYear: string;
    departmentMissingInBranch: string;
    noClassesInYear: string;
    guideFillRequired: string;
};

export type PlacementPayload = {
    academicYearId: number;
    classId: number;
    sectionId: number;
    branchId: number;
    departmentId: number;
    effectiveFrom: string;
    eligibleStudents: Array<{ id: number; full_name: string }>;
};

type PlacementFieldErrors = Partial<Record<PlacementFieldKey, string>>;

function emptyFieldErrors(
    students: Candidate[],
    draft: PlacementDraft,
    messages: PlacementValidationMessages,
): { fieldErrors: PlacementFieldErrors; details: string[] } {
    const required: Array<[PlacementFieldKey, string]> = [
        ['academic_year_id', messages.emptyYear],
        ['branch_id', messages.emptyBranch],
        ['department_id', messages.emptyDepartment],
        ['class_id', messages.emptyClass],
        ['section_id', messages.emptySection],
        ['effective_from', messages.emptyEffectiveFrom],
    ];
    const fieldErrors: PlacementFieldErrors = {};
    const details: string[] = students.length === 0 ? [messages.emptyStudents] : [];
    for (const [key, message] of required) {
        if (draft[key].trim() === '') {
            fieldErrors[key] = message;
            details.push(message);
        }
    }

    return { fieldErrors, details };
}

function issue(
    tone: EnrollmentDialogIssue['tone'],
    titleKey: EnrollmentDialogIssue['titleKey'],
    descriptionKey: string,
    fieldErrors?: PlacementFieldErrors,
    details?: string[],
): { ok: false; issue: EnrollmentDialogIssue } {
    return {
        ok: false,
        issue: {
            tone,
            titleKey,
            descriptionKey,
            details,
            fieldErrors,
        },
    };
}

/** Client pre-check before POST — the server re-validates every placement id. */
export function validatePlacement(input: {
    students: Candidate[];
    draft: PlacementDraft;
    filterOptions: EnrollmentFormFilterOptions;
    messages: PlacementValidationMessages;
}): { ok: true; payload: PlacementPayload } | { ok: false; issue: EnrollmentDialogIssue } {
    const { students, draft, filterOptions, messages } = input;

    const empty = emptyFieldErrors(students, draft, messages);
    if (empty.details.length > 0) {
        return issue('info', 'enrollGuideTitle', messages.guideFillRequired, empty.fieldErrors, empty.details);
    }

    if (filterOptions.classes.length === 0) {
        return issue('error', 'enrollDialogTitle', messages.noClassesInYear, { class_id: messages.noClassesInYear });
    }

    const alreadyRegistered = students.filter((student) => student.is_enrolled === true);
    if (alreadyRegistered.length > 0) {
        return issue(
            'error',
            'enrollAlreadyRegisteredTitle',
            messages.alreadyRegistered,
            undefined,
            alreadyRegistered.map((student) => messages.alreadyRegisteredNamed.replace('{name}', student.full_name)),
        );
    }

    if (!hasOption(placementBranchOptions(filterOptions), draft.branch_id)) {
        return issue('error', 'enrollDialogTitle', messages.branchMissingInYear, {
            branch_id: messages.branchMissingInYear,
        });
    }
    if (!hasOption(placementDepartmentOptions(filterOptions, draft.branch_id), draft.department_id)) {
        return issue('error', 'enrollDialogTitle', messages.departmentMissingInBranch, {
            department_id: messages.departmentMissingInBranch,
        });
    }
    if (!hasOption(placementClassOptions(filterOptions), draft.class_id)) {
        return issue('error', 'enrollDialogTitle', messages.classNotFound, { class_id: messages.classNotFound });
    }
    if (!hasOption(placementSectionOptions(filterOptions, draft.class_id), draft.section_id)) {
        return issue('error', 'enrollDialogTitle', messages.sectionNotFound, {
            section_id: messages.sectionNotFound,
        });
    }

    return {
        ok: true,
        payload: {
            academicYearId: Number(draft.academic_year_id),
            classId: Number(draft.class_id),
            sectionId: Number(draft.section_id),
            branchId: Number(draft.branch_id),
            departmentId: Number(draft.department_id),
            effectiveFrom: draft.effective_from.trim(),
            eligibleStudents: students
                .filter((student) => student.is_enrolled !== true)
                .map((student) => ({ id: student.id, full_name: student.full_name })),
        },
    };
}
