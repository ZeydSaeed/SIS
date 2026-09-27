import { describe, expect, it } from 'vitest';
import {
    resolveSisClassId,
    resolveSisSectionId,
} from '@/lib/sis-class-section-options';

describe('resolveSisClassId', () => {
    const classes = [
        { id: 1, code: 'CLS-1', name: 'الأول', grade_level_id: 1 },
        { id: 2, code: 'CLS-2', name: 'الثاني', grade_level_id: 1 },
    ];

    it('resolves Arabic class labels', () => {
        expect(resolveSisClassId('1', classes)).toBe(1);
        expect(resolveSisClassId('2', classes)).toBe(2);
    });

    it('returns null when class is missing', () => {
        expect(resolveSisClassId('3', classes)).toBeNull();
    });
});

describe('resolveSisSectionId', () => {
    const sections = [
        { id: 1, class_id: 1, code: 'SEC-A', name: 'شعبة أ' },
        { id: 2, class_id: 1, code: 'SEC-B', name: 'ب' },
        { id: 3, class_id: 2, code: 'SEC-2A', name: 'أ' },
    ];

    it('maps Latin A/B to Arabic section names and codes', () => {
        expect(resolveSisSectionId('A', 1, sections)).toBe(1);
        expect(resolveSisSectionId('B', 1, sections)).toBe(2);
        expect(resolveSisSectionId('A', 2, sections)).toBe(3);
    });

    it('returns null when section is missing for the class', () => {
        expect(resolveSisSectionId('C', 1, sections)).toBeNull();
    });
});
