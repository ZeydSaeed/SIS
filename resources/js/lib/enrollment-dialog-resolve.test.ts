import { describe, expect, it } from 'vitest';
import {
    admissionBranchSelectOptions,
    admissionClassSelectOptions,
    admissionDepartmentSelectOptions,
    classKeyFromAdmittedClassName,
    labelsLooselyMatch,
    normalizeArabicLabel,
    resolveBranchIdByName,
} from '@/lib/enrollment-dialog-resolve';

const branches = [
    { id: 3, code: 'BR-IND', name: 'الصناعي' },
    { id: 4, code: 'BR-AGR', name: 'الزراعي' },
];

describe('admission SSOT options', () => {
    it('exposes the same branch catalog as admission form', () => {
        expect(admissionBranchSelectOptions().map((item) => item.value)).toContain('الصناعي');
        expect(admissionDepartmentSelectOptions('الصناعي').map((item) => item.value)).toContain(
            'كهرباء',
        );
        expect(admissionClassSelectOptions().map((item) => item.value)).toEqual(['1', '2', '3']);
    });
});

describe('normalizeArabicLabel / labelsLooselyMatch', () => {
    it('matches كهرباء with الكهرباء', () => {
        expect(normalizeArabicLabel('الكهرباء')).toBe(normalizeArabicLabel('كهرباء'));
        expect(labelsLooselyMatch('كهرباء', 'الكهرباء')).toBe(true);
    });
});

describe('name → id resolution (student record form)', () => {
    it('resolves branch by name', () => {
        expect(resolveBranchIdByName('الصناعي', branches)).toBe(3);
    });

    it('maps admitted class name to catalog key', () => {
        expect(classKeyFromAdmittedClassName('الأول')).toBe('1');
    });
});
