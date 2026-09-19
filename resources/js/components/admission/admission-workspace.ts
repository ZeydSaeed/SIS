export type AdmissionPeriod = {
    id: number;
    academic_year_id: number;
    school_id: number;
    name: string;
    start_date: string;
    end_date: string;
    max_applications: number | null;
    status: number;
    created_at: string;
};

export type AdmissionApplication = {
    id: number;
    application_period_id: number;
    application_number: string;
    first_name: string;
    father_name: string | null;
    grandfather_name: string | null;
    great_grandfather_name: string | null;
    last_name: string;
    mother_name: string | null;
    maternal_father_name: string | null;
    maternal_grandfather_name: string | null;
    national_id: string | null;
    birth_date: string;
    birth_place: string | null;
    gender: number;
    target_school_id: number | null;
    grade_level_id: number | null;
    intended_grade_name: string | null;
    department_name: string | null;
    specialization_id: number | null;
    specialization_name: string | null;
    governorate: string | null;
    neighborhood: string | null;
    status: number;
    submitted_at: string | null;
    reviewed_by: number | null;
    reviewed_at: string | null;
    notes: string | null;
    student_id: number | null;
    created_at: string;
    updated_at: string;
    allowed_transitions: number[];
    can_convert: boolean;
};

export type AdmissionDocumentRow = {
    id: number;
    application_id: number;
    document_type: number;
    storage_key: string;
    file_name: string;
    file_hash: string;
    created_at: string;
};

export type AdmissionNamedOption = { id: number; name: string };

export type AdmissionWorkflowStep = { status: number; key: string };

export type AdmissionWorkflowStagePercent = { status: number; percent: number };

export type AdmissionWorkflowProgress = {
    overall_percent: number;
    stages: AdmissionWorkflowStagePercent[];
};

export type AdmissionActivePeriodSummary = {
    id: number;
    name: string;
    start_date?: string;
    max_applications: number | null;
    total_count: number;
    submitted_count: number;
    remaining: number | null;
};

export type AdmissionWorkspace = {
    periods: AdmissionPeriod[];
    applications: AdmissionApplication[];
    documents: AdmissionDocumentRow[];
    grade_levels: AdmissionNamedOption[];
    schools: AdmissionNamedOption[];
    departments: AdmissionNamedOption[];
    specializations: AdmissionNamedOption[];
    workflow_steps: AdmissionWorkflowStep[];
    workflow_progress?: AdmissionWorkflowProgress;
    active_periods?: AdmissionActivePeriodSummary[];
    selected_period_id?: number | null;
};

export type AdmissionPageAuthorization = {
    can_manage: boolean;
};

export const ADMISSION_STATUS_DRAFT = 1;
export const ADMISSION_STATUS_SUBMITTED = 2;
export const ADMISSION_STATUS_UNDER_REVIEW = 3;
export const ADMISSION_STATUS_INTERVIEW = 4;
export const ADMISSION_STATUS_WAITLISTED = 5;
export const ADMISSION_STATUS_ACCEPTED = 6;
export const ADMISSION_STATUS_CONVERTED = 9;
export const ADMISSION_STATUS_REQUEST = 0;

/** Stage workspace routes — draft/submitted + pipeline pages. */
export const ADMISSION_STAGE_PATHS: Record<number, string> = {
    [ADMISSION_STATUS_DRAFT]: '/admission/drafts',
    [ADMISSION_STATUS_SUBMITTED]: '/admission/submitted',
    [ADMISSION_STATUS_UNDER_REVIEW]: '/admission/under-review',
    [ADMISSION_STATUS_INTERVIEW]: '/admission/interview',
    [ADMISSION_STATUS_WAITLISTED]: '/admission/waitlisted',
    [ADMISSION_STATUS_ACCEPTED]: '/admission/accepted',
    [ADMISSION_STATUS_CONVERTED]: '/admission/converted',
};

export function admissionWorkspaceQuery(
    academicYearId: number | null | undefined,
    periodId?: number | null,
): string {
    const params = new URLSearchParams();
    if (academicYearId != null) {
        params.set('academic_year_id', String(academicYearId));
    }
    if (periodId != null && periodId > 0) {
        params.set('application_period_id', String(periodId));
    }
    const query = params.toString();

    return query === '' ? '' : `?${query}`;
}

export function admissionApplicationFullName(app: AdmissionApplication): string {
    return [
        app.first_name,
        app.father_name,
        app.grandfather_name,
        app.great_grandfather_name,
        app.last_name,
    ]
        .map((part) => part?.trim() ?? '')
        .filter((part) => part !== '')
        .join(' ');
}

export function lookupName(
    options: AdmissionNamedOption[],
    id: number | null,
    fallback: string | null = null,
): string {
    if (id !== null) {
        const match = options.find((option) => option.id === id);

        if (match) {
            return match.name;
        }
    }

    const text = fallback?.trim() ?? '';

    return text !== '' ? text : '—';
}
