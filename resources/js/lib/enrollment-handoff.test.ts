import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import {
    appendEnrollmentHandoff,
    clearEnrollmentHandoff,
    readEnrollmentHandoff,
    storeEnrollmentHandoff,
} from '@/lib/enrollment-handoff';

function installSessionStorageMock(): void {
    const store = new Map<string, string>();

    Object.defineProperty(globalThis, 'window', {
        configurable: true,
        value: {
            sessionStorage: {
                getItem: (key: string) => store.get(key) ?? null,
                setItem: (key: string, value: string) => {
                    store.set(key, value);
                },
                removeItem: (key: string) => {
                    store.delete(key);
                },
                clear: () => {
                    store.clear();
                },
            },
        },
    });
}

describe('enrollment-handoff', () => {
    beforeEach(() => {
        installSessionStorageMock();
    });

    afterEach(() => {
        clearEnrollmentHandoff();
    });

    it('stores and reads queued students for a year', () => {
        storeEnrollmentHandoff({
            academic_year_id: 12,
            students: [
                { id: 1, full_name: 'أحمد علي' },
                { id: 2, full_name: 'سارة محمد' },
            ],
        });

        expect(readEnrollmentHandoff()).toEqual({
            academic_year_id: 12,
            students: [
                { id: 1, full_name: 'أحمد علي' },
                { id: 2, full_name: 'سارة محمد' },
            ],
        });
    });

    it('merges students for the same year without duplicates', () => {
        storeEnrollmentHandoff({
            academic_year_id: 12,
            students: [{ id: 1, full_name: 'أحمد علي' }],
        });

        const count = appendEnrollmentHandoff({
            academic_year_id: 12,
            students: [
                { id: 1, full_name: 'أحمد علي محدّث' },
                { id: 3, full_name: 'نور حسن' },
            ],
        });

        expect(count).toBe(2);
        expect(readEnrollmentHandoff()?.students.map((student) => student.id)).toEqual([1, 3]);
        expect(readEnrollmentHandoff()?.students[0]?.full_name).toBe('أحمد علي محدّث');
    });

    it('replaces queue when academic year changes', () => {
        storeEnrollmentHandoff({
            academic_year_id: 12,
            students: [{ id: 1, full_name: 'قديم' }],
        });

        appendEnrollmentHandoff({
            academic_year_id: 13,
            students: [{ id: 9, full_name: 'جديد' }],
        });

        expect(readEnrollmentHandoff()).toEqual({
            academic_year_id: 13,
            students: [{ id: 9, full_name: 'جديد' }],
        });
    });
});
