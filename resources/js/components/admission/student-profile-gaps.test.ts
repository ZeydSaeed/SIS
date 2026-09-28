import { describe, expect, it } from 'vitest';
import {
    isStudentProfileComplete,
    studentNeedsRegistrationContinuation,
    studentProfileCompletenessGaps,
} from '@/components/admission/student-profile-gaps';
import {
    STUDENT_REQUIRED_DOCUMENT_TYPES,
    studentRecordCompletenessGaps,
} from '@/components/students/student-record-gaps';
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

function allDocuments(): Array<{ id: number; document_type: number; file_name: string }> {
    return STUDENT_REQUIRED_DOCUMENT_TYPES.map((slot, index) => ({
        id: index + 1,
        document_type: slot.type,
        file_name: `doc-${slot.type}.jpg`,
    }));
}

/** Complete civil+study profile without email/notes (excluded from count). */
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
        branch_id: 1,
        branch_name: 'الصناعي',
        department_name: 'الميكانيك',
        admitted_class_name: 'Grade 11',
        school_start_date: '2026-09-01',
        previous_school_name: 'مدرسة سابقة',
        transfer_document_number: 100,
        transfer_document_date: '2026-08-01',
        graduation_year: 2025,
        previous_gpa: 85,
        previous_study_track: 1,
        father_occupation: 'موظف',
        mother_occupation: 'ربة منزل',
        administrative_unit: 1,
        guardian_triple_name: 'أحمد علي حسن',
        mobile: '7700000000',
        guardian_mobile: '7700000001',
        email: null,
        notes: null,
        documents: allDocuments(),
        status: 1,
    };
}

describe('studentProfileCompletenessGaps', () => {
    it('lists empty required fields and excludes email/notes', () => {
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
                notes: null,
                documents: [],
                status: 1,
            },
        });

        const gaps = studentProfileCompletenessGaps(app);

        expect(gaps).toContain('اسم أب الأم');
        expect(gaps).toContain('محل الولادة');
        expect(gaps).toContain('الجنسية');
        expect(gaps).toContain('صورة شخصية');
        expect(gaps).not.toContain('البريد الإلكتروني');
        expect(gaps).not.toContain('الملاحظات');
        expect(isStudentProfileComplete(app)).toBe(false);
        expect(studentNeedsRegistrationContinuation(app)).toBe(true);
    });

    it('complete without email/notes → مستوفي', () => {
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

    it('counts each missing document slot when documents key is present', () => {
        const record = {
            ...completeRecord(),
            documents: [{ id: 1, document_type: 20, file_name: 'photo.jpg' }],
        };

        const gaps = studentRecordCompletenessGaps(record);
        expect(gaps).toHaveLength(STUDENT_REQUIRED_DOCUMENT_TYPES.length - 1);
        expect(gaps).not.toContain('صورة شخصية');
        expect(gaps).toContain('البطاقة الوطنية للطالب (الوجه الأول)');
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

    it('does not count blank math/physics grades (hidden when empty)', () => {
        const record = {
            ...completeRecord(),
            mathematics_grade: null,
            physics_grade: null,
        };

        expect(studentRecordCompletenessGaps(record)).toEqual([]);
    });
});
