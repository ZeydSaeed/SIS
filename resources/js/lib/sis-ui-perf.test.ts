import { describe, expect, it } from 'vitest';
import {
    isDirtyPayloadEmpty,
    pickDirtyPayload,
    sisToggleQueryFlag,
    sisValuesEqual,
} from '@/lib/sis-ui-perf';

describe('sis-ui-perf', () => {
    it('treats empty string and null as equal', () => {
        expect(sisValuesEqual('', null)).toBe(true);
        expect(sisValuesEqual('a', 'a')).toBe(true);
        expect(sisValuesEqual(1, '1')).toBe(false);
    });

    it('always includes required anchors and omits unchanged optionals', () => {
        const baseline = {
            first_name: 'أحمد',
            last_name: 'علي',
            birth_date: '2010-01-01',
            gender: 1,
            notes: 'قديم',
            mobile: '7700000000',
        };
        const next = {
            first_name: 'أحمد',
            last_name: 'علي',
            birth_date: '2010-01-01',
            gender: 2,
            notes: 'قديم',
            mobile: '7700000000',
        };

        const payload = pickDirtyPayload(baseline, next, {
            always: ['first_name', 'last_name', 'birth_date'],
        });

        expect(payload).toEqual({
            first_name: 'أحمد',
            last_name: 'علي',
            birth_date: '2010-01-01',
            gender: 2,
        });
        expect(isDirtyPayloadEmpty(payload, ['first_name', 'last_name', 'birth_date'])).toBe(false);
    });

    it('includes whole group when any member is dirty', () => {
        const baseline = {
            first_name: 'أ',
            last_name: 'ب',
            birth_date: '2010-01-01',
            national_id: '1',
            mobile: '2',
            email: null,
        };
        const next = {
            first_name: 'أ',
            last_name: 'ب',
            birth_date: '2010-01-01',
            national_id: '1',
            mobile: '3',
            email: null,
        };

        const payload = pickDirtyPayload(baseline, next, {
            always: ['first_name', 'last_name', 'birth_date'],
            groups: [['national_id', 'mobile', 'email']],
        });

        expect(payload.mobile).toBe('3');
        expect(payload.national_id).toBe('1');
        expect(payload.email).toBe(null);
    });

    it('toggles deferred-load query flags without dropping path', () => {
        expect(sisToggleQueryFlag('/admission?academic_year_id=1', 'include_accepted_roster', true)).toBe(
            '/admission?academic_year_id=1&include_accepted_roster=1',
        );
        expect(
            sisToggleQueryFlag(
                '/admission?academic_year_id=1&include_accepted_roster=1',
                'include_accepted_roster',
                false,
            ),
        ).toBe('/admission?academic_year_id=1');
    });
});
