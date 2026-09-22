import type { AdmissionApplication } from '@/components/admission/admission-workspace';
import {
    isStudentRecordComplete,
    studentRecordCompletenessGaps,
} from '@/components/students/student-record-gaps';

function admissionGapSource(app: AdmissionApplication): Record<string, unknown> {
    const record =
        app.student_record && typeof app.student_record === 'object'
            ? (app.student_record as Record<string, unknown>)
            : null;

    if (record !== null) {
        return record;
    }

    return app as unknown as Record<string, unknown>;
}

/** Empty student-form field labels for converted applicants. */
export function studentProfileCompletenessGaps(app: AdmissionApplication): string[] {
    return studentRecordCompletenessGaps(admissionGapSource(app));
}

/** Converted applicant with a student row and zero profile gaps. */
export function isStudentProfileComplete(app: AdmissionApplication): boolean {
    return Boolean(app.student_id) && isStudentRecordComplete(admissionGapSource(app));
}

/** Converted applicant still missing required student-form fields. */
export function studentNeedsRegistrationContinuation(app: AdmissionApplication): boolean {
    return Boolean(app.student_id) && studentProfileCompletenessGaps(app).length > 0;
}
