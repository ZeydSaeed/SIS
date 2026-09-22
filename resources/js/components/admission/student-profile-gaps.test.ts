import { describe, expect, it } from 'vitest';
import {
    isStudentProfileComplete,
    studentNeedsRegistrationContinuation,
    studentProfileCompletenessGaps,
} from '@/components/admission/student-profile-gaps';
import { studentRecordCompletenessGaps } from '@/components/students/student-record-gaps';
import type { AdmissionApplication } from '@/components/admission/admission-workspace';

function baseApp(overrides: Partial<AdmissionApplication> = {}): AdmissionApplication {
    return {
        id: 1,
        application_period_id: 1,
        application_number: 'APP-1',
        first_name: 'إسراء',
        father_name: 'جواد',
        grandfather_name: 'مواس',
        great_grandfather_name: 'عباس',
        last_name: 'التميمي',
        mother_name: 'إيمان',
        maternal_father_name: null,
        maternal_grandfather_name: null,
        national_id: '199000100158',
        birth_date: '2009-03-24',
        birth_place: null,
        gender: 2,
        target_school_id: 1,
        branch_id: null,
        grade_level_id: 1,
        intended_grade_name: 'أول',
        department_name: null,
        specialization_id: null,
        specialization_name: null,
        governorate: 'بابل',
        neighborhood: null,
        status: 9,
        submitted_at: null,
        reviewed_by: null,
        reviewed_at: null,
        notes: null,
        student_id: 10,
        created_at: '2026-01-01',
        updated_at: '2026-01-01',
        ...overrides,
    };
}

/** Complete civil+study profile without transfer or email (email optional). */
function completeRecord(): Record<string, unknown> {
    return {
        id: 10,
        student_code: 'STU-1',
        first_name: 'إسراء',
        father_name: 'جواد',
        grandfather_name: 'مواس',
        great_grandfather_name: 'عباس',
        last_name: 'التميمي',
        mother_name: 'إيمان',
        maternal_father_name: 'حسن',
        maternal_grandfather_name: 'علي',
        birth_date: '2009-03-24',
        birth_place: 'الحلة',
        gender: 2,
        nationality: 'عراقي',
        religion: 1,
        national_id: '199000100158',
        mawalid_date: '2009-04-01',
        registration_place: 'الحلة',
        governorate: 'بابل',
        neighborhood: 'الحي',
        locality: 'المحلة',
        house_number: '12',
        school_name: 'مدرسة النور',
        academic_year_id: 12,
        school_start_date: '2026-09-01',
        previous_school_name: null,
        transfer_document_number: null,
        transfer_document_date: null,
        guardian_triple_name: 'أحمد علي حسن',
        mobile: '7700000000',
        guardian_mobile: '7700000001',
        email: null,
        status: 1,
    };
}

describe('studentProfileCompletenessGaps', () => {
    it('lists empty required fields (transfer/email not required when empty)', () => {
        const app = baseApp({
            student_record: {
                id: 10,
                student_code: 'STU-1',
                first_name: 'إسراء',
                father_name: 'جواد',
                grandfather_name: 'مواس',
                great_grandfather_name: 'عباس',
                last_name: 'التميمي',
                mother_name: 'إيمان',
                maternal_father_name: null,
                maternal_grandfather_name: null,
                birth_date: '2009-03-24',
                birth_place: null,
                gender: 2,
                nationality: null,
                religion: 1,
                national_id: '199000100158',
                mawalid_date: null,
                registration_place: null,
                governorate: 'بابل',
                neighborhood: null,
                locality: null,
                house_number: null,
                school_name: null,
                academic_year_id: 12,
                school_start_date: null,
                previous_school_name: null,
                transfer_document_number: null,
                transfer_document_date: null,
                guardian_triple_name: null,
                mobile: null,
                guardian_mobile: null,
                email: null,
                status: 1,
            },
        });

        const gaps = studentProfileCompletenessGaps(app);

        expect(gaps).toContain('اسم أب الأم');
        expect(gaps).toContain('محل الولادة');
        expect(gaps).toContain('الجنسية');
        expect(gaps).not.toContain('اسم المدرسة التي نُقل منها');
        expect(gaps).not.toContain('البريد الإلكتروني');
        expect(gaps).toHaveLength(14);
        expect(isStudentProfileComplete(app)).toBe(false);
        expect(studentNeedsRegistrationContinuation(app)).toBe(true);
    });

    it('complete without transfer/email → مستوفي', () => {
        const record = completeRecord();
        const app = baseApp({ student_record: record });

        expect(studentRecordCompletenessGaps(record)).toEqual([]);
        expect(studentProfileCompletenessGaps(app)).toEqual([]);
        expect(isStudentProfileComplete(app)).toBe(true);
        expect(studentNeedsRegistrationContinuation(app)).toBe(false);
    });

    it('does not treat PII stripped from list payload as gaps', () => {
        const record = completeRecord();
        delete record.national_id;
        delete record.mobile;
        delete record.guardian_mobile;
        delete record.email;

        expect(studentRecordCompletenessGaps(record)).toEqual([]);
    });

    it('requires full transfer set when any transfer field is filled', () => {
        const record = {
            ...completeRecord(),
            previous_school_name: 'مدرسة سابقة',
            transfer_document_number: null,
            transfer_document_date: null,
        };

        const gaps = studentRecordCompletenessGaps(record);
        expect(gaps).toContain('رقم وثيقة النقل');
        expect(gaps).toContain('تاريخ وثيقة النقل');
    });

    it('missing academic_year_id key (legacy list) is not a false gap', () => {
        const record = completeRecord();
        delete record.academic_year_id;

        expect(studentRecordCompletenessGaps(record)).toEqual([]);
    });

    it('null academic_year_id when key is present is a gap', () => {
        const record = { ...completeRecord(), academic_year_id: null };

        expect(studentRecordCompletenessGaps(record)).toContain('السنة الدراسية');
    });
});
