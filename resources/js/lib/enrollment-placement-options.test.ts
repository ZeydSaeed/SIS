import { describe, expect, it } from 'vitest';
import {
    placementBranchOptions,
    placementClassOptions,
    placementDepartmentOptions,
    placementSectionOptions,
    prefillPlacement,
    validatePlacement,
    type PlacementDraft,
} from '@/lib/enrollment-placement-options';

const filterOptions = {
    branches: [
        { id: 4, code: 'BR-AGR', name: 'الزراعي' },
        { id: 3, code: 'BR-IND', name: 'الصناعي' },
    ],
    classes: [
        { id: 12, code: 'CLS-2', name: 'الثاني', grade_level_id: 2 },
        { id: 11, code: 'CLS-1', name: 'الأول', grade_level_id: 1 },
    ],
    sections: [
        { id: 21, class_id: 11, code: 'SEC-A', name: 'شعبة أ' },
        { id: 22, class_id: 11, code: 'SEC-B', name: 'ب' },
        { id: 23, class_id: 12, code: 'SEC-A', name: 'شعبة أ' },
    ],
    departments: [
        { id: 31, branch_id: 3, code: 'DEP-ELEC', name: 'كهرباء' },
        { id: 39, branch_id: 3, code: 'DEP-VOC', name: 'كهرباء' },
        { id: 32, branch_id: 3, code: 'DEP-MECH', name: 'ميكانيك' },
        { id: 40, branch_id: 4, code: 'DEP-AGR', name: 'زراعي' },
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
    alreadyRegistered: 'alreadyRegistered',
    alreadyRegisteredNamed: 'already:{name}',
    classNotFound: 'classNotFound',
    sectionNotFound: 'sectionNotFound',
    branchMissingInYear: 'branchMissingInYear',
    departmentMissingInBranch: 'departmentMissingInBranch',
    noClassesInYear: 'noClassesInYear',
    guideFillRequired: 'guideFillRequired',
};

const validDraft: PlacementDraft = {
    academic_year_id: '1',
    branch_id: '3',
    department_id: '31',
    class_id: '11',
    section_id: '21',
    effective_from: '2026-09-01',
};

describe('placement options (ids from server, established labels)', () => {
    it('orders branches by the admission catalog and uses record ids', () => {
        expect(placementBranchOptions(filterOptions)).toEqual([
            { value: '3', label: 'الصناعي' },
            { value: '4', label: 'الزراعي' },
        ]);
    });

    it('lists only the branch departments and collapses legacy duplicates', () => {
        expect(placementDepartmentOptions(filterOptions, '3')).toEqual([
            { value: '31', label: 'كهرباء' },
            { value: '32', label: 'ميكانيك' },
        ]);
        expect(placementDepartmentOptions(filterOptions, '')).toEqual([]);
    });

    it('keeps catalog class and section labels (الأول / A, B)', () => {
        expect(placementClassOptions(filterOptions)).toEqual([
            { value: '11', label: 'الأول' },
            { value: '12', label: 'الثاني' },
        ]);
        expect(placementSectionOptions(filterOptions, '11')).toEqual([
            { value: '21', label: 'A' },
            { value: '22', label: 'B' },
        ]);
        expect(placementSectionOptions(filterOptions, '')).toEqual([]);
    });
});

describe('prefillPlacement', () => {
    it('prefers structured ids saved on the student', () => {
        expect(
            prefillPlacement(
                [{ id: 1, full_name: 'علي', branch_id: 3, department_id: 32, grade_level_id: 2 }],
                filterOptions,
            ),
        ).toEqual({ branch_id: '3', department_id: '32', class_id: '12', section_id: '' });
    });

    it('falls back to exact names when ids are missing', () => {
        expect(
            prefillPlacement(
                [
                    {
                        id: 1,
                        full_name: 'علي',
                        branch_id: 3,
                        department_name: 'كهرباء',
                        admitted_class_name: 'الأول',
                        section_name: 'A',
                    },
                ],
                filterOptions,
            ),
        ).toEqual({ branch_id: '3', department_id: '31', class_id: '11', section_id: '21' });
    });
});

describe('validatePlacement', () => {
    it('accepts a complete placement and returns numeric ids', () => {
        const result = validatePlacement({
            students: [{ id: 7, full_name: 'علي' }],
            draft: validDraft,
            filterOptions,
            messages,
        });
        expect(result).toEqual({
            ok: true,
            payload: {
                academicYearId: 1,
                classId: 11,
                sectionId: 21,
                branchId: 3,
                departmentId: 31,
                effectiveFrom: '2026-09-01',
                eligibleStudents: [{ id: 7, full_name: 'علي' }],
            },
        });
    });

    it('guides empty required fields', () => {
        const result = validatePlacement({
            students: [{ id: 7, full_name: 'علي' }],
            draft: { ...validDraft, department_id: '', section_id: '' },
            filterOptions,
            messages,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.issue.titleKey).toBe('enrollGuideTitle');
            expect(result.issue.fieldErrors).toEqual({
                department_id: 'emptyDepartment',
                section_id: 'emptySection',
            });
        }
    });

    it('rejects a department of another branch and a section of another class', () => {
        const wrongDepartment = validatePlacement({
            students: [{ id: 7, full_name: 'علي' }],
            draft: { ...validDraft, department_id: '40' },
            filterOptions,
            messages,
        });
        expect(!wrongDepartment.ok && wrongDepartment.issue.descriptionKey).toBe('departmentMissingInBranch');

        const wrongSection = validatePlacement({
            students: [{ id: 7, full_name: 'علي' }],
            draft: { ...validDraft, section_id: '23' },
            filterOptions,
            messages,
        });
        expect(!wrongSection.ok && wrongSection.issue.descriptionKey).toBe('sectionNotFound');
    });

    it('rejects already enrolled students', () => {
        const result = validatePlacement({
            students: [{ id: 7, full_name: 'علي', is_enrolled: true }],
            draft: validDraft,
            filterOptions,
            messages,
        });
        expect(!result.ok && result.issue.details).toEqual(['already:علي']);
    });
});
