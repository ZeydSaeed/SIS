import { describe, expect, it } from 'vitest';
import {
    admissionBranchSelectOptions,
    admissionClassSelectOptions,
    admissionDepartmentSelectOptions,
    admissionSectionSelectOptions,
    classKeyFromAdmittedClassName,
    draftPlacementFromStudents,
    labelsLooselyMatch,
    normalizeArabicLabel,
    resolveBranchIdByName,
    resolveDepartmentIdByName,
    validateEnrollmentDialog,
} from '@/lib/enrollment-dialog-resolve';

const filterOptions = {
    branches: [
        { id: 3, code: 'BR-IND', name: 'الصناعي' },
        { id: 4, code: 'BR-AGR', name: 'الزراعي' },
    ],
    classes: [
        { id: 1, code: 'CLS-1', name: 'الأول', grade_level_id: 1 },
        { id: 2, code: 'CLS-2', name: 'الثاني', grade_level_id: 1 },
    ],
    sections: [
        { id: 1, class_id: 1, code: 'SEC-A', name: 'شعبة أ' },
        { id: 2, class_id: 1, code: 'SEC-B', name: 'ب' },
        { id: 3, class_id: 2, code: 'SEC-2A', name: 'أ' },
    ],
    departments: [
        { id: 3, branch_id: 3, code: 'DEP-ELEC', name: 'الكهرباء' },
        { id: 4, branch_id: 3, code: 'DEP-MECH', name: 'الميكانيك' },
        { id: 6, branch_id: 4, code: 'DEP-CROPS', name: 'المحاصيل' },
    ],
    specializations: [],
};

const messages = {
    emptyStudents: 'emptyStudents',
    emptyYear: 'emptyYear',
    emptyBranch: 'emptyBranch',
    emptyDepartment: 'emptyDepartment',
    emptyClass: 'emptyClass',
    emptySection: 'emptySection',
    emptyEffectiveFrom: 'emptyEffectiveFrom',
    emptyFields: 'emptyFields',
    alreadyRegistered: 'alreadyRegistered',
    alreadyRegisteredNamed: 'already:{name}',
    classNotFound: 'classNotFound',
    sectionNotFound: 'sectionNotFound',
    branchRequiredWithDept: 'branchRequiredWithDept',
    branchMissingInYear: 'branchMissingInYear',
    departmentMissingInBranch: 'departmentMissingInBranch',
    noClassesInYear: 'noClassesInYear',
    noSectionsForClass: 'noSectionsForClass',
    guideFillRequired: 'guideFillRequired',
    classLabel: 'class',
    sectionLabel: 'section',
};

describe('admission SSOT options', () => {
    it('exposes the same branch catalog as admission form', () => {
        expect(admissionBranchSelectOptions().map((item) => item.value)).toContain('الصناعي');
        expect(admissionDepartmentSelectOptions('الصناعي').map((item) => item.value)).toContain(
            'كهرباء',
        );
        expect(admissionClassSelectOptions().map((item) => item.value)).toEqual(['1', '2', '3']);
        expect(admissionSectionSelectOptions().map((item) => item.value)).toEqual(['A', 'B', 'C']);
    });
});

describe('normalizeArabicLabel / labelsLooselyMatch', () => {
    it('matches كهرباء with الكهرباء', () => {
        expect(normalizeArabicLabel('الكهرباء')).toBe(normalizeArabicLabel('كهرباء'));
        expect(labelsLooselyMatch('كهرباء', 'الكهرباء')).toBe(true);
    });
});

describe('name → id resolution', () => {
    it('resolves branch and department by loose Arabic match', () => {
        expect(resolveBranchIdByName('الصناعي', filterOptions.branches)).toBe(3);
        expect(
            resolveDepartmentIdByName('كهرباء', 3, filterOptions.departments),
        ).toBe(3);
    });
});

describe('validateEnrollmentDialog scenarios', () => {
    const baseDraft = {
        academic_year_id: '1',
        branch_name: 'الصناعي',
        department_name: 'كهرباء',
        class_key: '1',
        section_code: 'A',
        effective_from: '2026-09-01',
    };

    it('1) rejects empty students', () => {
        const result = validateEnrollmentDialog({
            students: [],
            draft: baseDraft,
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.tone).toBe('warning');
            expect(result.issue.details).toContain('emptyStudents');
        }
    });

    it('2) marks empty required fields', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: {
                ...baseDraft,
                branch_name: '',
                department_name: '',
                class_key: '',
                section_code: '',
                effective_from: '',
            },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.fieldErrors).toEqual({
                branch_name: 'emptyBranch',
                department_name: 'emptyDepartment',
                class_key: 'emptyClass',
                section_code: 'emptySection',
                effective_from: 'emptyEffectiveFrom',
            });
        }
    });

    it('3) rejects already enrolled student', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي', is_enrolled: true }],
            draft: baseDraft,
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.tone).toBe('error');
            expect(result.issue.details?.[0]).toBe('already:علي');
        }
    });

    it('4) rejects missing class in year', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: { ...baseDraft, class_key: '3' },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.fieldErrors?.class_key).toBe('classNotFound');
        }
    });

    it('5) rejects unavailable section for class', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: { ...baseDraft, class_key: '1', section_code: 'C' },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.fieldErrors?.section_code).toBe('sectionNotFound');
        }
    });

    it('6) requires branch when empty', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: { ...baseDraft, branch_name: '', department_name: 'كهرباء' },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.fieldErrors?.branch_name).toBe('emptyBranch');
        }
    });

    it('7) rejects department not belonging to branch', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: {
                ...baseDraft,
                branch_name: 'الصناعي',
                department_name: 'المحاصيل',
            },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.fieldErrors?.department_name).toBe('departmentMissingInBranch');
        }
    });

    it('8) accepts valid enrollment with required branch/department', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: baseDraft,
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.payload.classId).toBe(1);
            expect(result.payload.sectionId).toBe(1);
            expect(result.payload.branchId).toBe(3);
            expect(result.payload.departmentId).toBe(3);
        }
    });

    it('9) accepts catalog كهرباء mapped to DB الكهرباء', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: {
                ...baseDraft,
                branch_name: 'الصناعي',
                department_name: 'كهرباء',
            },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(true);
        if (result.ok) {
            expect(result.payload.branchId).toBe(3);
            expect(result.payload.departmentId).toBe(3);
        }
    });

    it('10) rejects when year has no classes', () => {
        const result = validateEnrollmentDialog({
            students: [{ id: 10, full_name: 'علي' }],
            draft: baseDraft,
            filterOptions: { ...filterOptions, classes: [], sections: [] },
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.descriptionKey).toBe('noClassesInYear');
        }
    });
});

describe('draftPlacementFromStudents', () => {
    it('prefills branch/department/class from saved student placement', () => {
        expect(classKeyFromAdmittedClassName('الأول')).toBe('1');
        expect(
            draftPlacementFromStudents(
                [
                    {
                        branch_id: 3,
                        department_name: 'الكهرباء',
                        admitted_class_name: 'الأول',
                        section_name: 'شعبة أ',
                    },
                ],
                filterOptions.branches,
            ),
        ).toEqual({
            branch_name: 'الصناعي',
            department_name: 'كهرباء',
            class_key: '1',
            section_code: 'A',
        });
    });
});
